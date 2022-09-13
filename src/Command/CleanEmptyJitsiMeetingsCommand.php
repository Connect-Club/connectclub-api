<?php

namespace App\Command;

use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Service\EventLogManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanEmptyJitsiMeetingsCommand extends Command
{
    protected static $defaultName = 'CleanEmptyJitsiMeetings';

    private VideoMeetingRepository $videoMeetingRepository;
    private EventLogManager $eventLogManager;

    public function __construct(VideoMeetingRepository $videoMeetingRepository, EventLogManager $eventLogManager)
    {
        $this->videoMeetingRepository = $videoMeetingRepository;
        $this->eventLogManager = $eventLogManager;

        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this
            ->setDescription('Clean empty jitsi meetings')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var VideoMeeting[] $meetings */
        $meetings = $this->videoMeetingRepository->createQueryBuilder('m')
            ->where('m.endTime IS NULL')
            ->andWhere('m.jitsiCounter = 0')
            ->andWhere(time().' - m.startTime >= 3600')
            ->andWhere('m.isEmptyMeeting = false')
            ->getQuery()
            ->getResult();

        foreach ($meetings as $meeting) {
            if ($meeting->videoRoom->type != VideoRoom::TYPE_NATIVE) {
                continue;
            }

            $meeting->endTime = time();
            $this->videoMeetingRepository->save($meeting);
            $this->eventLogManager->logEvent($meeting, 'auto_close_meeting_from_clean_empty_jitsi_meeting');
        }

        $output->writeln(sprintf('Automatic close %d jitsi meetings', count($meetings)));

        return 0;
    }
}
