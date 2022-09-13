<?php

namespace App\Command;

use App\Client\ElasticSearchClientBuilder;
use App\Entity\User;
use App\Message\UploadUserToElasticsearchMessage;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class SyncUsersElasticsearchCommand extends Command
{
    private MessageBusInterface $bus;
    private UserRepository $userRepository;
    private ElasticSearchClientBuilder $elasticSearchClientBuilder;

    protected static $defaultName = 'SyncUsersElasticsearchCommand';
    protected static $defaultDescription = 'Sync all unsynchronized users with elasticsearch';

    public function __construct(
        MessageBusInterface $bus,
        UserRepository $userRepository,
        ElasticSearchClientBuilder $elasticSearchClientBuilder
    ) {
        parent::__construct(self::$defaultName);

        $this->bus = $bus;
        $this->userRepository = $userRepository;
        $this->elasticSearchClientBuilder = $elasticSearchClientBuilder;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $client = $this->elasticSearchClientBuilder->createClient();

        $testUsers = $this->userRepository->matching(
            Criteria::create()->where(
                Criteria::create()->where(
                    Criteria::expr()->eq('isTester', true)
                )->orWhere(
                    Criteria::expr()->neq('state', User::STATE_VERIFIED)
                )->getWhereExpression()
            )->andWhere(
                Criteria::expr()->neq('uploadToElasticSearchAt', null)
            )
        )->toArray();

        /** @var User $testUser */
        foreach ($testUsers as $testUser) {
            $output->writeln('Delete from elasticsearch '.$testUser->id);

            $testUser->uploadToElasticSearchAt = null;
            $this->userRepository->save($testUser);

            try {
                $client->delete(['index' => 'user', 'id' => $testUser->id, 'type' => '_doc']);
            } catch (Throwable $exception) {
                $output->writeln('Deletion error '.$exception->getMessage());
            }
        }

        $unsynchronizedUsers = $this->userRepository->findBy([
            'uploadToElasticSearchAt' => null,
            'state' => User::STATE_VERIFIED,
            'isTester' => false,
        ]);

        $io->writeln(sprintf('Unsynchronized users: %d', count($unsynchronizedUsers)));

        $io->progressStart(count($unsynchronizedUsers));
        foreach ($unsynchronizedUsers as $unsynchronizedUser) {
            $this->bus->dispatch(new UploadUserToElasticsearchMessage($unsynchronizedUser));
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->writeln('Done');

        return Command::SUCCESS;
    }
}
