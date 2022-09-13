<?php

namespace App\Command;

use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Service\JitsiEndpointManager;
use App\Service\SubscriptionService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class KickSubscribersWithOutdatedPaymentConfirmationCommand extends Command
{
    use LockableTrait;

    private EntityManagerInterface $entityManager;
    private JitsiEndpointManager $jitsiEndpointManager;
    private SubscriptionService $subscriptionService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        JitsiEndpointManager $jitsiEndpointManager,
        SubscriptionService $subscriptionService,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->jitsiEndpointManager = $jitsiEndpointManager;
        $this->subscriptionService = $subscriptionService;
        $this->logger = $logger;

        parent::__construct('app:kick-subscribers-with-outdated-payment-confirmation');
    }

    protected function configure()
    {
        $this
            ->setDescription('Kick subscribers with outdated payment confirmation')
            ->addOption('iteration-size', null, InputOption::VALUE_REQUIRED, '', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        $iterationSize = (int) $input->getOption('iteration-size');

        $this->resetActiveSubscriptionWaitingFor();

        $this->stopSqlLogging();

        $query = $this->createSelectQuery($iterationSize);

        $totalCount = $this->createCountQuery()
            ->getSingleScalarResult();

        $io->progressStart($totalCount);

        $kickedUserCount = 0;

        do {
            $lastId = null;
            $count = 0;
            /** @var PaidSubscription $paidSubscription */
            foreach ($query->getResult() as $paidSubscription) {
                try {
                    $count++;

                    if ($this->subscriptionService->isConfirmationOutdated($paidSubscription)) {
                        $this->kick($paidSubscription->subscriber, $paidSubscription->subscription);

                        $kickedUserCount++;
                    }

                    $paidSubscription->waitingForPaymentConfirmationUpTo = null;
                    $lastId = $paidSubscription->id;
                } catch (Throwable $e) {
                    $this->logger->error("Error during processing payment confirmation: {$e->getMessage()}");
                }
            }

            $query->setParameter('lastId', $lastId);
            $this->entityManager->flush();
            $this->entityManager->clear();

            $io->progressAdvance($count);
        } while ($count >= $iterationSize);

        $this->release();

        $io->progressFinish();

        $io->success("Kicked users: {$kickedUserCount}");

        return Command::SUCCESS;
    }

    private function resetActiveSubscriptionWaitingFor(): void
    {
        $query = $this->entityManager->createQueryBuilder()
            ->update(PaidSubscription::class, 'paidSubscription')
            ->set('paidSubscription.waitingForPaymentConfirmationUpTo', 'null')
            ->where('paidSubscription.waitingForPaymentConfirmationUpTo IS NOT NULL')
            ->andWhere('paidSubscription.status IN (:active)')
            ->setParameter('active', PaidSubscription::getActiveStatuses(), Connection::PARAM_INT_ARRAY)
            ->getQuery();

        $query->execute();
    }

    private function createCountQuery(): Query
    {
        return $this->createCommonQueryBuilder()
            ->select('COUNT(paidSubscription.id)')
            ->getQuery();
    }

    private function createSelectQuery(int $iterationSize): Query
    {
        return $this->createCommonQueryBuilder()
            ->select('paidSubscription')
            ->join('paidSubscription.subscriber', 'subscriber')
            ->join('paidSubscription.subscription', 'subscription')
            ->leftJoin('subscription.videoRooms', 'videoRooms')
            ->andWhere('paidSubscription.id > :lastId')
            ->orderBy('paidSubscription.id', 'ASC')
            ->setParameter('lastId', 0)
            ->setMaxResults($iterationSize)
            ->getQuery();
    }

    private function createCommonQueryBuilder(): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb
            ->from(PaidSubscription::class, 'paidSubscription')
            ->where('paidSubscription.waitingForPaymentConfirmationUpTo IS NOT NULL')
            ->andWhere('
                paidSubscription.status <> :incomplete
                OR paidSubscription.waitingForPaymentConfirmationUpTo < :time
            ')
            ->setParameter('incomplete', PaidSubscription::STATUS_INCOMPLETE)
            ->setParameter('time', time());

        return $qb;
    }

    private function stopSqlLogging(): void
    {
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger();
    }

    private function kick(User $user, Subscription $subscription): void
    {
        foreach ($subscription->videoRooms as $videoRoom) {
            try {
                $this->jitsiEndpointManager->disconnectUserFromRoom($user, $videoRoom);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Cannot kick user #$user->id from video room #$videoRoom->id. Reason: {$e->getMessage()}"
                );
            }
        }
    }
}
