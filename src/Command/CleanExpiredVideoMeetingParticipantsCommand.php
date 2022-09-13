<?php

namespace App\Command;

use App\ConnectClub;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Service\EventLogManager;
use App\Service\JitsiEndpointManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanExpiredVideoMeetingParticipantsCommand extends Command
{
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private JitsiEndpointManager $jitsiEndpointManager;
    private EventLogManager $eventLogManager;

    protected static $defaultName = 'CleanExpiredVideoMeetingParticipants';

    public function __construct(
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        JitsiEndpointManager $jitsiEndpointManager,
        EventLogManager $eventLogManager
    ) {
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->jitsiEndpointManager = $jitsiEndpointManager;
        $this->eventLogManager = $eventLogManager;

        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this
            ->setDescription('Clean expired video meeting participants')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $participants = $this->videoMeetingParticipantRepository
            ->createQueryBuilder('p')
            ->join('p.participant', 'u')
            ->where(time().' - p.startTime >= '.ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT)
            ->andWhere('p.endTime IS NULL')
            ->andWhere('u.isHost = false')
            ->getQuery()
            ->getResult();

        $disabledUsers = [];

        $io = new SymfonyStyle($input, $output);
        $io->progressStart(count($participants));

        /** @var VideoMeetingParticipant $participant */
        foreach ($participants as $participant) {
            try {
                $this->jitsiEndpointManager->disconnectUserFromRoom(
                    $participant->participant,
                    $participant->videoMeeting->videoRoom
                );

                $this->eventLogManager->logEvent($participant, 'disconnect_participant_from_video_room');
            } catch (Exception $exception) {
                $participant->endTime = time();
                $this->videoMeetingParticipantRepository->save($participant);

                $this->eventLogManager->logEvent(
                    $participant,
                    'disconnect_participant_manually_from_video_room_error',
                    ['message' => $exception->getMessage()]
                );
            }

            $io->progressAdvance();

            $disabledUsers[] = [
                $participant->videoMeeting->id,
                $participant->participant->id,
                $participant->videoMeeting->videoRoom->community->description
            ];
        }

        $io->progressFinish();

        $io->table(['Meeting id', 'Disconnected user', 'Community'], $disabledUsers);

        return 0;
    }
}
