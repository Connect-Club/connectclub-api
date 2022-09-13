<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\VideoRoomImageObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectImageDataResponse
{
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $src = '';

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $title = null;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $description = null;

    public function __construct(VideoRoomImageObject $videoRoomImageObject)
    {
        $this->src = $videoRoomImageObject->photo->getOriginalUrl();
        $this->title = $videoRoomImageObject->title;
        $this->description = $videoRoomImageObject->description;
    }
}
