<?php

namespace App\Message;

use App\ConnectClub;
use App\Entity\VideoChat\VideoRoom;

final class CheckAvatarPhotoTheHiveAiMessage
{
    private ?int $photoId = null;
    private string $photoUrl;
    private int $userId;
    private ?string $videoRoomLink = null;

    public function __construct(int $photoId, string $photoUrl, int $userId, ?VideoRoom $videoRoom = null)
    {
        $this->photoId = $photoId;
        $this->photoUrl = $photoUrl;
        $this->userId = $userId;

        if ($videoRoom) {
            $this->videoRoomLink = ConnectClub::shortVideoRoomLink($videoRoom);
        }
    }

    public function getPhotoId(): ?int
    {
        return $this->photoId;
    }

    public function getPhotoUrl(): string
    {
        return $this->photoUrl;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getVideoRoomLink(): ?string
    {
        return $this->videoRoomLink;
    }
}
