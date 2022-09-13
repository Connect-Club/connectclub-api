<?php

namespace App\Service;

use App\Controller\ErrorCode;
use App\Entity\Community\Community;
use App\Entity\Event\EventDraft;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Community\CommunityRepository;
use App\Repository\Event\EventDraftRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VideoRoomManager
{
    private CommunityRepository $communityRepository;
    private EventDraftRepository $eventDraftRepository;

    public function __construct(CommunityRepository $communityRepository, EventDraftRepository $eventDraftRepository)
    {
        $this->communityRepository = $communityRepository;
        $this->eventDraftRepository = $eventDraftRepository;
    }

    public function createVideoRoomByType(string $type, User $owner, ?string $description = null): VideoRoom
    {
        $eventDraft = $this->eventDraftRepository->findOneBy(['type' => $type]);

        if (!$eventDraft) {
            throw new RuntimeException('Event draft with code private not found, private room not working');
        }

        return $this->createVideoRoomFromDraft($eventDraft, $owner, $description);
    }

    public function createVideoRoomFromDraft(EventDraft $draft, User $owner, ?string $description = null): VideoRoom
    {
        $name = uniqid();
        while ($this->communityRepository->findOneBy(['name' => $name])) {
            $name = uniqid();
        }

        $community = new Community($owner, $name, $description);
        $videoRoom = $community->videoRoom;
        $videoRoom->type = VideoRoom::TYPE_NEW;

        $videoRoom->config->backgroundRoomHeightMultiplier = $draft->backgroundRoomHeightMultiplier;
        $videoRoom->config->backgroundRoomWidthMultiplier = $draft->backgroundRoomWidthMultiplier;
        $videoRoom->config->backgroundRoom = $draft->backgroundPhoto;
        $videoRoom->config->initialRoomScale = $draft->initialRoomScale;
        $videoRoom->config->publisherRadarSize = $draft->publisherRadarSize;
        $videoRoom->config->withSpeakers = $draft->withSpeakers;
        $videoRoom->config->maxRoomZoom = $draft->maxRoomZoom;
        $videoRoom->maxParticipants = $draft->maxParticipants;
        $videoRoom->draftType = $draft->type;

        return $videoRoom;
    }
}
