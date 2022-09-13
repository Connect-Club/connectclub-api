<?php

namespace App\EventSubscriber;

use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomParticipantConnectedEvent;
use App\Service\JabberEndpointManager;
use App\Service\NetworkingManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ParticipantConnectedJoinCommunitySubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function onVideoRoomParticipantConnectedEvent(VideoRoomParticipantConnectedEvent $event)
    {
        if (!$user = $event->user) {
            $this->logger->error('Participant not found for join group chat', $event->getContext());
            return;
        }

        if ($event->videoRoom->type !== VideoRoom::TYPE_NEW) {
            return;
        }

        $event->videoRoom->community->addParticipant($user);

        $this->entityManager->persist($event->videoRoom->community);
        $this->entityManager->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomParticipantConnectedEvent::class => 'onVideoRoomParticipantConnectedEvent',
        ];
    }
}
