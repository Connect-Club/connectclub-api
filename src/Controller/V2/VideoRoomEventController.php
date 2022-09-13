<?php

namespace App\Controller\V2;

use App\Controller\BaseController;
use App\DTO\V2\VideoRoom\JitsiVideoRoomEventRequest;
use App\Entity\VideoRoom\VideoRoomParticipantStatistic;
use App\Event\VideoRoomCreatedEvent;
use App\Event\VideoRoomEndedEvent;
use App\Event\VideoRoomEvent;
use App\Event\VideoRoomEventParameters;
use App\Event\VideoRoomParticipantConnectedEvent;
use App\Event\VideoRoomParticipantDisconnectedEvent;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Repository\VideoRoom\VideoRoomParticipantStatisticRepository;
use App\Service\EventLogManager;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use MaxMind\Db\Reader;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RoomController.
 *
 * @Route("/video-room")
 */
class VideoRoomEventController extends BaseController
{
    private Reader $reader;
    private LoggerInterface $logger;
    private VideoRoomRepository $videoRoomRepository;

    /**
     * VideoRoomEventController constructor.
     */
    public function __construct(
        Reader $reader,
        VideoRoomRepository $videoRoomRepository,
        LoggerInterface $logger
    ) {
        $this->reader = $reader;
        $this->logger = $logger;
        $this->videoRoomRepository = $videoRoomRepository;
    }

    /**
     * @SWG\Post(
     *     produces={"application/json"},
     *     description="Video chat room event (jitsi)",
     *     summary="Video chat room event  (jitsi)",
     *     @SWG\Response(response="200", description="Success create room"),
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         @SWG\Schema(ref=@Model(type=JitsiVideoRoomEventRequest::class))
     *     ),
     *     tags={"Video Room"}
     * )
     * @ViewResponse()
     * @Route("/event", methods={"POST"}, defaults={"id": "\d+"}, name="api_v2_room_events")
     */
    public function event(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        VideoRoomRepository $videoRoomRepository,
        EventLogManager $eventLogManager,
        VideoMeetingRepository $videoMeetingRepository,
        VideoRoomParticipantStatisticRepository $videoRoomParticipantStatisticRepository
    ) {
        if ($entityManager->getFilters()->isEnabled('softdeleteable')) {
            $entityManager->getFilters()->disable('softdeleteable');
        }

        /** @var JitsiVideoRoomEventRequest $eventRequest */
        $eventRequest = $this->getEntityFromRequestTo($request, JitsiVideoRoomEventRequest::class);

        $logContext = json_decode($request->getContent(), true);
        $videoRoom = $videoRoomRepository->findOneByName($eventRequest->conferenceGid);

        if ($videoRoom) {
            $eventLogManager->logEvent($videoRoom, 'request_from_jitsi', $logContext);
        }

        if (mb_strpos($eventRequest->endpointId, 'screen-') !== false &&
            mb_strpos($eventRequest->endpointId, 'service-')) {
            return $this->handleResponse([]);
        }

        $event = $eventRequest->eventType;
        $user = $userRepository->find((int) $eventRequest->endpointId);
        $meeting = $videoMeetingRepository->findOneBy(['sid' => $eventRequest->conferenceId]);
        if (!$meeting && $videoRoom) {
            $activeMeeting = $videoRoom->getActiveMeeting();
            if ($activeMeeting && $activeMeeting->initiator == VideoRoomEvent::INITIATOR_JITSI) {
                $meeting = $activeMeeting;
            }
        }

        if (!$videoRoom) {
            $this->logger->error(sprintf('Video room event %s room not found', $event), $logContext);
            return $this->handleResponse([]);
        }

        if ($meeting) {
            $eventLogManager->logEvent($videoRoom, 'meeting_chosen_as_active');
        }

        $eventParameters = new VideoRoomEventParameters(
            $eventRequest->conferenceId,
            $eventRequest->endpointId,
            $eventRequest->conferenceGid
        );

        switch ($eventRequest->eventType) {
            case 'CONFERENCE_CREATED':
                $eventDispatcher->dispatch(new VideoRoomCreatedEvent(
                    $videoRoom,
                    $meeting,
                    $user,
                    $eventParameters,
                    time(),
                    VideoRoomEvent::INITIATOR_JITSI,
                    $eventRequest->endpointUuid
                ));
                break;
            case 'CONFERENCE_EXPIRED':
                $eventDispatcher->dispatch(new VideoRoomEndedEvent(
                    $videoRoom,
                    $meeting,
                    $user,
                    $eventParameters,
                    time(),
                    VideoRoomEvent::INITIATOR_JITSI,
                    $eventRequest->endpointUuid
                ));
                break;
            case 'ENDPOINT_CREATED':
                $eventDispatcher->dispatch(new VideoRoomParticipantConnectedEvent(
                    $eventRequest->endpointAllowIncomingMedia ?? false,
                    $videoRoom,
                    $meeting,
                    $user,
                    $eventParameters,
                    time(),
                    VideoRoomEvent::INITIATOR_JITSI,
                    $eventRequest->endpointUuid
                ));
                break;
            case 'ENDPOINT_EXPIRED':
                $eventDispatcher->dispatch(new VideoRoomParticipantDisconnectedEvent(
                    $videoRoom,
                    $meeting,
                    $user,
                    $eventParameters,
                    time(),
                    VideoRoomEvent::INITIATOR_JITSI,
                    $eventRequest->endpointUuid
                ));
                break;
            case 'ENDPOINT_SERVER_STATS':
                if ($user) {
                    $videoMeetingParticipantStatistic = new VideoRoomParticipantStatistic(
                        $videoRoom,
                        $user,
                        $eventRequest->endpointUuid,
                        $eventRequest->conferenceId,
                        $eventRequest->payload['rtt'] ?? 0,
                        $eventRequest->payload['jitter'] ?? 0,
                        $eventRequest->payload['cumulativePacketsLost'] ?? 0,
                        $eventRequest->createdAt ?? time()
                    );

                    $videoRoomParticipantStatisticRepository->save($videoMeetingParticipantStatistic);
                }
                break;
        }

        $entityManager->getFilters()->enable('softdeleteable');

        return $this->handleResponse([]);
    }
}
