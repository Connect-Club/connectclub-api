<?php

namespace App\DTO\V1\VideoRoom;

use App\DTO\V1\User\UserInfoResponse;
use App\DTO\V1\User\UserResponse;
use App\DTO\V1\User\VideoRoomHistoryAuthorResponse;
use App\Entity\User;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomHistoryItemResponse
{
    /**
     * @var int
     * @Groups({"api.v1.video_room.history"})
     */
    public int $id;

    /**
     * @var string
     * @Groups({"api.v1.video_room.history"})
     */
    public string $name;

    /**
     * @var string|null
     * @Groups({"api.v1.video_room.history"})
     */
    public ?string $description;

    /**
     * @var string
     * @Groups({"api.v1.video_room.history"})
     */
    public string $password;

    /**
     * @var string|null
     * @Groups({"api.v1.video_room.history"})
     */
    public ?string $chatRoomName;

    /**
     * @var VideoRoomHistoryAuthorResponse
     * @Groups({"api.v1.video_room.history"})
     */
    public VideoRoomHistoryAuthorResponse $author;

    /**
     * @var int
     * @Groups({"api.v1.video_room.history"})
     */
    public int $countOnline;

    /**
     * @var UserInfoResponse[]
     * @Groups({"api.v1.video_room.history"})
     */
    public array $onlineUsers;

    /**
     * @var int
     * @Groups({"api.v1.video_room.history"})
     */
    public int $joinedAt;

    /**
     * @var string
     * @Groups({"api.v1.video_room.history"})
     */
    public string $resizerUrl;

    /**
     * @var bool
     * @Groups({"api.v1.video_room.history"})
     */
    public bool $open;

    public function __construct(
        int $id,
        bool $open,
        string $name,
        string $password,
        ?string $description,
        ?string $chatRoomName,
        User $author,
        array $onlineUsers,
        int $joinedAt,
        string $resizerUrl
    ) {
        $this->id = $id;
        $this->open = $open;
        $this->name = $name;
        $this->password = $password;
        $this->description = $description;
        $this->chatRoomName = $chatRoomName;
        $this->author = new VideoRoomHistoryAuthorResponse($author);
        $this->onlineUsers = $onlineUsers;
        $this->countOnline = count($onlineUsers);
        $this->joinedAt = $joinedAt;
        $this->resizerUrl = $resizerUrl;
    }
}
