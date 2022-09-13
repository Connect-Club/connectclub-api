<?php

namespace App\Command;

use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\EventLogManager;
use App\Service\JitsiEndpointManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CloseRoomWithoutModeratorsCommand extends Command
{
    protected static $defaultName = 'CloseRoomWithoutModeratorsCommand';
    protected static $defaultDescription = 'Kill all participants in rooms without moderators';

    private VideoRoomRepository $videoRoomRepository;
    private JitsiEndpointManager $jitsiEndpointManager;
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private EventLogManager $eventLogManager;
    private LoggerInterface $logger;

    public function __construct(
        VideoRoomRepository $videoRoomRepository,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        JitsiEndpointManager $jitsiEndpointManager,
        EventLogManager $eventLogManager,
        LoggerInterface $logger
    ) {
        parent::__construct(self::$defaultName);

        $this->videoRoomRepository = $videoRoomRepository;
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->jitsiEndpointManager = $jitsiEndpointManager;
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;
    }


    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $videoRoomsWithoutModerators = $this->videoRoomRepository->findOnlineRoomsWithoutModerators();
        $io->writeln(sprintf('Count of rooms without moderators: %d', count($videoRoomsWithoutModerators)));

        $log = [];

        foreach ($videoRoomsWithoutModerators as $videoRoomWithoutModerators) {
            $activeMeeting = $videoRoomWithoutModerators->getActiveMeeting();

            if (!$activeMeeting) {
                $log[] = [$videoRoomWithoutModerators->community->name, 'No active meeting'];
                continue;
            }

            $onlineUsers = $activeMeeting->participants
                ->filter(fn(VideoMeetingParticipant $p) => $p->endTime === null)
                ->map(fn(VideoMeetingParticipant $p) => $p->participant);

            $log[] = [
                $videoRoomWithoutModerators->community->name,
                sprintf('Online users count %d', count($onlineUsers))
            ];

            foreach ($onlineUsers as $onlineUser) {
                try {
                    $this->jitsiEndpointManager->disconnectUserFromRoom($onlineUser, $videoRoomWithoutModerators);
                    $loggingContext = [];
                } catch (Exception $exception) {
                    $this->logger->error($exception, ['exception' => $exception]);
                    $loggingContext = [$exception->getTraceAsString()];
                }

                $videoRoomWithoutModerators->doneAt = time();
                $this->videoRoomRepository->save($videoRoomWithoutModerators);

                $this->eventLogManager->logEvent(
                    $videoRoomWithoutModerators,
                    'disconnect_user_from_video_room_without_moderators',
                    array_merge(['userId' => $onlineUser->id], $loggingContext)
                );
            }
        }

        $io->table(['Video room', 'Reason'], $log);

        return Command::SUCCESS;
    }
}
