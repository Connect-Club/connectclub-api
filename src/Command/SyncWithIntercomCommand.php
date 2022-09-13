<?php

namespace App\Command;

use App\Client\IntercomClient;
use App\Entity\User;
use App\Exception\IntercomContactAlreadyExistsException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

class SyncWithIntercomCommand extends Command
{
    protected static $defaultName = 'SyncWithIntercomCommand';
    protected static $defaultDescription = 'Sync users with intercom';

    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private LockFactory $lockFactory;
    private IntercomClient $intercomClient;
    private LoggerInterface $logger;

    public function __construct(
        UserRepository $userRepository,
        IntercomClient $intercomClient,
        LockFactory $lockFactory,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct(self::$defaultName);

        $this->userRepository = $userRepository;
        $this->intercomClient = $intercomClient;
        $this->lockFactory = $lockFactory;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->lockFactory->createLock('command_'.self::$defaultName)->acquire()) {
            $io->warning('Lock failed');
            return Command::SUCCESS;
        }

        if ($_ENV['STAGE'] == 1) {
            return Command::SUCCESS;
        }

        $slug = $this->userRepository->findUserWithClubs();

        $lastValue = null;
        [,,$totalCount] = $this->userRepository->findVerifiedUsers(null, 200);
        $io->progressStart($totalCount);
        do {
            [$users, $lastValue] = $this->userRepository->findVerifiedUsers($lastValue, 200);
            $this->logger->warning('Last value '.$lastValue);

            /** @var User $user */
            foreach ($users as $user) {
                $clubSlugs = '';
                foreach ($slug[$user->id] ?? [] as $clubSlug) {
                    $clubSlugs .= '|'.$clubSlug.'|';
                }
                $customAttributes = ['club_slug' => $clubSlugs];
                $calculatedIntercomHash = $this->intercomClient->getContactHash($user, $customAttributes);

                if (!$user->intercomId && $user->intercomHash !== $calculatedIntercomHash) {
                    try {
                        $user->intercomHash = $calculatedIntercomHash;

                        $intercomData = $this->intercomClient->registerContact($user, $customAttributes);
                        $user->intercomId = $intercomData['id'];
                    } catch (IntercomContactAlreadyExistsException $exception) {
                        $user->intercomId = $this->intercomClient->findIntercomContact($user)['id'];
                    }
                } else {
                    $this->intercomClient->updateContact($user, ['club_slug' => $clubSlugs]);
                }

                $this->entityManager->persist($user);
                $io->progressAdvance();
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();
        } while ($lastValue !== null);


        $io->progressFinish();

        return Command::SUCCESS;
    }
}
