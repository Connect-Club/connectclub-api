<?php

namespace App\DTO\V1\VideoRoom;

use App\Annotation\SerializationContext;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\VideoRoomObject;
use Swagger\Annotations as SWG;

class BackgroundWithObjectsResponse
{
    /**
     * @var VideoRoomBackgroundResponse
     */
    public VideoRoomBackgroundResponse $background;

    /**
     * @var VideoRoomObject[]
     * @SerializationContext(serializeAsObject=true)
     * @SWG\Property(type="object")
     */
    public array $objects = [];

    /**
     * @var object[]
     * @SerializationContext(serializeAsObject=true)
     * @SWG\Property(type="object")
     */
    public array $objectsData = [];

    public function __construct(BackgroundPhoto $background)
    {
        $this->background = new VideoRoomBackgroundResponse($background);
        list($this->objects, $this->objectsData) = VideoRoomObjectListResponse::getObjectsAndObjectsData(
            $background->objects->toArray()
        );
    }
}
