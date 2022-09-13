<?php

namespace App\EventSubscriber\Twilio;

use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Event\SlackNotificationEvent;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Service\EventLogManager;
use App\Service\SlackClient;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnRoomEndedSlackNotificationSubscriber implements EventSubscriberInterface
{
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private VideoMeetingRepository $videoMeetingRepository;
    private SlackClient $slackClient;
    private EventLogManager $eventLogManager;
    private LoggerInterface $logger;

    public function __construct(
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        VideoMeetingRepository $videoMeetingRepository,
        SlackClient $slackClient,
        EventLogManager $eventLogManager,
        LoggerInterface $logger
    ) {
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->videoMeetingRepository = $videoMeetingRepository;
        $this->slackClient = $slackClient;
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;
    }

    public function onVideoRoomEndedEvent(SlackNotificationEvent $event)
    {
        $videoRoomActiveMeeting = $event->getVideoMeeting();
        $perParticipantDuration = [];

        /** @var VideoMeetingParticipant $participant */
        foreach ($videoRoomActiveMeeting->participants->toArray() as $participant) {
            $name = $participant->participant->getFullNameOrId() . '(id '.$participant->participant->id.')';
            $perParticipantDuration[$name] = ($perParticipantDuration[$name] ?? 0) + $participant->getDuration();
        }

        $uniqueParticipants = array_unique(
            array_map(
                fn (VideoMeetingParticipant $participant) => $participant->participant->getId(),
                $videoRoomActiveMeeting->participants->toArray()
            )
        );

        $uniqueGuests = array_unique(array_keys($videoRoomActiveMeeting->videoRoom->guests ?? []));

        $guestDuration = [];
        foreach ($videoRoomActiveMeeting->videoRoom->guests ?? [] as [$guestId, $startTime, $endTime]) {
            if (!isset($guestDuration[$guestId])) {
                $guestDuration[$guestId] = 0;
            }

            $guestDuration[$guestId] += $endTime - $startTime;
        }

        $roomDurationMinutes = ceil($videoRoomActiveMeeting->getDuration() / 60);
        $message = $videoRoomActiveMeeting->videoRoom->community->description.' ended.';
        $message .= ' Duration in minutes: '.$roomDurationMinutes;
        $message .= ' Unique participants (users) count: '.count($uniqueParticipants)."\r\n";
        $message .= ' Unique guests count: '.count($uniqueGuests)."\r\n";
        $message .= ' Total unique sessions: '.(count($uniqueGuests) + count($uniqueParticipants))."\r\n";

        $messageParticipantDuration = "Per user duration:\n";
        foreach ($perParticipantDuration as $userFullName => $value) {
            $min = ceil($value / 60);
            $messageParticipantDuration .= $userFullName.": ".$min." min\n";
        }

        $messageParticipantDuration .= "\r\n";
        $messageParticipantDuration .= "Per guest duration:\n";
        foreach ($guestDuration as $guestId => $duration) {
            $minutes = ceil($duration / 60);
            $messageParticipantDuration .= $guestId.": ".$minutes." min\n";
        }

        $participants = $videoRoomActiveMeeting->participants;
        $startTime = min($participants->map(fn(VideoMeetingParticipant $p) => $p->startTime)->toArray());
        $endTime = max($participants->map(fn(VideoMeetingParticipant $p) => $p->endTime)->toArray());

        $chunkMinutes = array_map(
            fn($chunk) => [min($chunk), max($chunk)],
            array_chunk(range($startTime, $endTime), 60)
        );

        $messageStrParticipants = "Per minute:\n";
        foreach ($chunkMinutes as $minute => list($minMinuteInterval, $maxMinuteInterval)) {
            $minuteParticipantNames = [];

            foreach ($videoRoomActiveMeeting->participants as $participant) {
                if ($participant->startTime <= $maxMinuteInterval && $participant->endTime >= $minMinuteInterval) {
                    $minuteParticipantNames[] = $participant->participant->getFullNameOrId();
                }
            }

            foreach ($videoRoomActiveMeeting->videoRoom->guests as [$guestId, $startTime, $endTime]) {
                if ($startTime <= $maxMinuteInterval && $endTime >= $minMinuteInterval) {
                    $minuteParticipantNames[] = 'Guest '.$guestId;
                }
            }

            $messageStrParticipants .= ($minute + 1).": ".implode(", ", $minuteParticipantNames)."\n";
        }

        try {
            $this->slackClient->sendMessageWithThread(
                '#'.$_ENV['SLACK_CHANNEL_STATISTICS_NAME'],
                $message,
                $messageParticipantDuration,
                $messageStrParticipants
            );
        } catch (Exception $exception) {
            $this->logger->error($exception);
        }

        $this->eventLogManager->logEvent($videoRoomActiveMeeting, 'conference_expired_slack_notification');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SlackNotificationEvent::class => 'onVideoRoomEndedEvent',
        ];
    }
}
