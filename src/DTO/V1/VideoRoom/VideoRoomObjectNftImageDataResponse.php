<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectNftImageDataResponse
{
    /**
     * @Groups({"default"})
     */
    public string $src = '';

    /**
     * @Groups({"default"})
     */
    public ?string $title = null;

    /**
     * @Groups({"default"})
     */
    public ?string $description = null;

    public function __construct(VideoRoomNftImageObject $videoRoomNftImageObject)
    {
        $this->src = $videoRoomNftImageObject->photo->getResizerUrl();
        $this->title = $videoRoomNftImageObject->title;
        $this->description = $videoRoomNftImageObject->description;
    }
}
