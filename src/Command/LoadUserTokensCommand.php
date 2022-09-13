<?php

namespace App\Command;

use App\Client\GoogleCloudStorageClient;
use App\Client\NftImageClient;
use App\Entity\Ethereum\UserToken;
use App\Entity\Photo\NftImage;
use App\Entity\User;
use App\Repository\Ethereum\UserTokenRepository;
use App\Service\MatchingClient;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Throwable;

class LoadUserTokensCommand extends Command
{
    use LockableTrait;

    private const DOWNLOAD_LIMIT = 300;
    private const REDIS_LAST_USER_KEY = 'last_user_nft_downloaded_for_v2';

    protected static $defaultName = 'LoadUserTokensCommand';
    protected static $defaultDescription = 'Load user tokens command';

    private EntityManagerInterface $entityManager;
    private MatchingClient $matchingClient;
    private LoggerInterface $logger;
    private GoogleCloudStorageClient $googleStorage;
    private NftImageClient $nftImageClient;
    private UserTokenRepository $userTokenRepository;
    private Redis $redis;
    private LockFactory $lockFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        MatchingClient $client,
        LoggerInterface $logger,
        GoogleCloudStorageClient $googleStorage,
        NftImageClient $nftImageClient,
        UserTokenRepository $userTokenRepository,
        Redis $redis,
        LockFactory $lockFactory
    ) {
        $this->entityManager = $entityManager;
        $this->matchingClient = $client;
        $this->logger = $logger;
        $this->googleStorage = $googleStorage;
        $this->nftImageClient = $nftImageClient;
        $this->userTokenRepository = $userTokenRepository;
        $this->redis = $redis;
        $this->lockFactory = $lockFactory;

        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock(self::$defaultName);

        if (!$lock->acquire()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $lastUserProcessedId = $this->redis->get(self::REDIS_LAST_USER_KEY);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.wallet IS NOT NULL')
            ->orderBy('u.id', 'ASC')
        ;

        if (false !== $lastUserProcessedId) {
            $qb->andWhere('u.id > :lastUserProcessedId')
                ->setParameter('lastUserProcessedId', $lastUserProcessedId)
            ;
        }

        $users = $qb->getQuery()->getResult();

        $sf = new SymfonyStyle($input, $output);
        $sf->progressStart(count($users));

        $j = 0;
        $promises = [];
        foreach ($users as $j => $user) {
            try {
                $tokensData = $this->matchingClient->findTokensForUser($user);
            } catch (RequestException $e) {
                $this->logger->warning(sprintf('Error while fetching tokens for user %s', $user->getId()));
                continue;
            }

            $sf->writeln(sprintf('User %s handle %d nfts', $user->id, count($tokensData['data'])));
            foreach ($tokensData['data'] ?? [] as $tokenData) {
                $sf->writeln('Handle token '.$tokenData['image']);

                if (count($this->userTokenRepository->findBy(['tokenId' => $tokenData['tokenId']])) > 0) {
                    $sf->warning($tokenData['tokenId'] . ' skip');
                    continue;
                }

                $userToken = new UserToken();
                $userToken->tokenId = $tokenData['tokenId'];
                $userToken->user = $user;
                $userToken->name = $tokenData['name'];
                $userToken->description = $tokenData['description'];

                if (preg_match('/^data:image\/(.*);base64,(.*)$/m', $tokenData['image'], $matches)) {
                    $extensionMap = [
                        'png' => 'png',
                        'jpeg' => 'jpg',
                        'jpg' => 'jpg',
                        'gif' => 'gif',
                        'bmp' => 'bmp',
                        'vnd.microsoft.icon' => 'ico',
                        'tiff' => 'tiff',
                        'svg+xml' => 'svg',
                    ];

                    $ext = $matches[1] ?? null;
                    if (!isset($extensionMap[$ext])) {
                        $this->logger->warning('Not found extension '.$ext);
                        continue;
                    }

                    $fileExtension = $extensionMap[$ext];

                    $file = tmpfile();
                    fwrite($file, file_get_contents($matches[0]));
                    $tmpFilePath = stream_get_meta_data($file)['uri'];

                    $storageImage = $this->googleStorage->uploadFile(
                        $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'],
                        $tmpFilePath,
                        $userToken->tokenId . '.' . $fileExtension
                    );
                    $userToken->nftImage = new NftImage(
                        $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'],
                        $storageImage['object'],
                        $storageImage['object'],
                        $user
                    );

                    $this->entityManager->persist($userToken);
                    $this->entityManager->flush();

                    fclose($file);
                } else {
                    try {
                        [$response, $tmpFilePath] = $this->nftImageClient->syncDownload($tokenData['image']);
                        $googleBucket = $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'];
                        $fileExtension = NftImageClient::getFileExtension($response);
                        $storageImage = $this->googleStorage->uploadFile(
                            $googleBucket,
                            $tmpFilePath,
                            $userToken->tokenId . '.' . $fileExtension
                        );
                        $userToken->nftImage = new NftImage(
                            $googleBucket,
                            $storageImage['object'],
                            $storageImage['object'],
                            $user
                        );

                        $this->entityManager->persist($userToken);
                        $this->entityManager->flush();
                    } catch (Throwable $e) {
                        $this->logger->warning(
                            sprintf(
                                'Error while loading user nft image for user %s: %s with token %s %s',
                                $user->getId(),
                                $userToken->tokenId,
                                $e->getMessage(),
                                $tokenData['image'] ?? ''
                            )
                        );
                    }
                }

                $sf->writeln('Done handle token '.$tokenData['image']);

                if ($j >= self::DOWNLOAD_LIMIT) {
                    // next time we will start from the same user
                    break;
                }
                $this->redis->set(self::REDIS_LAST_USER_KEY, $user->getId());
            }

            $sf->writeln(sprintf('Done user %s handle %d nfts', $user->id, count($tokensData['data'])));
            $sf->writeln('===========================================================================');
            $sf->write(PHP_EOL);

            $sf->progressAdvance();
        }
        $sf->progressFinish();

        // no more users, next time we will start from the first user
        if ($j < self::DOWNLOAD_LIMIT) {
            $this->redis->unlink(self::REDIS_LAST_USER_KEY);
            $sf->writeln('No more users');
        }

        return Command::SUCCESS;
    }
}
