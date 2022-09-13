<?php

namespace App\Command;

use App\Repository\Log\EventLogRelationRepository;
use App\Repository\Log\EventLogRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanEventLogDataCommand extends Command
{
    protected static $defaultName = 'CleanEventLogData';

    private EventLogRepository $eventLogRepository;
    private EventLogRelationRepository $eventLogRelationRepository;

    public function __construct(
        EventLogRepository $eventLogRepository,
        EventLogRelationRepository $eventLogRelationRepository
    ) {
        $this->eventLogRepository = $eventLogRepository;
        $this->eventLogRelationRepository = $eventLogRelationRepository;

        parent::__construct(self::$defaultName);
    }


    protected function configure()
    {
        $this->setDescription('Clean event log data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rowsRelations = $this->eventLogRelationRepository
            ->createQueryBuilder('el')
            ->delete()
            ->where(time() . '- el.time > ' . (3600 * 24 * 14))
            ->getQuery()
            ->execute();

        $rowsLogs = $this->eventLogRepository
             ->createQueryBuilder('el')
             ->delete()
             ->where(time() . '- el.time > ' . (3600 * 24 * 14))
             ->getQuery()
             ->execute();

        $output->writeln('Removed event log relations: '.$rowsRelations);
        $output->writeln('Removed event log: '.$rowsLogs);

        return 0;
    }
}
