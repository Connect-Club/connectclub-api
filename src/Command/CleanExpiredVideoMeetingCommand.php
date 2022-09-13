<?php

namespace App\Command;

use App\ConnectClub;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Service\EventLogManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanExpiredVideoMeetingCommand extends Command
{
    protected static $defaultName = 'CleanExpiredVideoMeetingCommand';

    private VideoMeetingRepository $videoMeetingRepository;
    private EventLogManager $eventLogManager;
    private LoggerInterface $logger;

    public function __construct(
        VideoMeetingRepository $videoMeetingRepository,
        EventLogManager $eventLogManager,
        LoggerInterface $logger
    ) {
        $this->videoMeetingRepository = $videoMeetingRepository;
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;

        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this
            ->setDescription('Clean expired video meetings')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $meetings = $this->videoMeetingRepository->findActiveMeetings();

        $io = new SymfonyStyle($input, $output);
        $io->progressStart(count($meetings));

        foreach ($meetings as $meeting) {
            $onlineParticipants = $meeting->getUniqueParticipants()->filter(
                fn (VideoMeetingParticipant $participant) => null === $participant->endTime
            );

            if ($onlineParticipants->count() > 0 ||
                $meeting->isEmptyMeeting ||
                $meeting->videoRoom->type != VideoRoom::TYPE_NATIVE) {
                $io->progressAdvance();
                continue;
            }

            if (time() - $meeting->startTime >= ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT) {
                $this->doneMeeting($meeting, time());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        return 0;
    }

    private function doneMeeting(VideoMeeting $meeting, int $timestamp)
    {
        $meeting->endTime = $timestamp;
        $this->videoMeetingRepository->save($meeting);

        $eventContext = $meeting->participants->map(
            fn(VideoMeetingParticipant $p) => ['id' => $p->id, 'start' => $p->startTime, 'end' => $p->endTime]
        )->toArray();

        $this->eventLogManager->logEvent($meeting, 'auto_close_meeting_from_clean_expired_meetings', $eventContext);
    }
}
