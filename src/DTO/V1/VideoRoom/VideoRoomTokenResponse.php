<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomConfig;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomTokenResponse
{
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $token;

    /**
     * @var VideoRoomConfigResponse
     * @Groups({"default"})
     */
    public VideoRoomConfigResponse $config;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $name;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $description;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $id;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $sid;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $ownerId;

    /**
     * @var bool
     * @Groups({"default"})
     */
    public bool $open;

    public function __construct(
        string $token,
        VideoRoom $videoRoom,
        string $name,
        ?string $description,
        int $id,
        string $sid,
        int $ownerId,
        bool $open
    ) {
        $this->token = $token;
        $this->name = $name;
        $this->description = $description;
        $this->id = $id;
        $this->sid = $sid;
        $this->ownerId = $ownerId;
        $this->config = VideoRoomConfigResponse::createFromVideoRoomConfig($videoRoom);
        $this->open = $open;
    }
}
