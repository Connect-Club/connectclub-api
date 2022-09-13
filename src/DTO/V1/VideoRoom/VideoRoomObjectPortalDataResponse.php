<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectPortalDataResponse
{
    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $name;
    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $password;

    public function __construct(?string $name, ?string $password)
    {
        $this->name = $name;
        $this->password = $password;
    }

    public static function createFromObject(VideoRoomPortalObject $videoRoomObject): VideoRoomObjectPortalDataResponse
    {
        return new self($videoRoomObject->name, $videoRoomObject->password);
    }
}
