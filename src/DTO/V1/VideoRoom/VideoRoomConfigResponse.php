<?php

namespace App\DTO\V1\VideoRoom;

use App\Annotation\SerializationContext;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use App\Entity\VideoChat\Object\VideoRoomSquarePortalObject;
use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomConfig;
use App\Entity\VideoChat\VideoRoomObject;
use App\Entity\VideoChat\VideoRoomQuality;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomConfigResponse
{
    /**
     * @var int|null
     * @Groups({"default"})
     */
    public ?int $id;

    /**
     * @var VideoRoomBackgroundResponse|null
     * @Groups({"default"})
     */
    public ?VideoRoomBackgroundResponse $backgroundRoom = null;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $backgroundRoomWidthMultiplier;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $backgroundRoomHeightMultiplier;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $initialRoomScale;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $minRoomZoom;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $maxRoomZoom;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $videoBubbleSize;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $publisherRadarSize;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $intervalToSendDataTrackInMilliseconds;

    /**
     * @var VideoRoomQuality
     * @Groups({"default"})
     */
    public VideoRoomQuality $videoQuality;

    /**
     * @var Location
     * @Groups({"default"})
     */
    public Location $speakerLocation;

    /**
     * @SerializationContext(serializeAsObject=true)
     * @Groups({"default"})
     * @var VideoRoomConfigObjectResponse[]
     */
    public array $objects = [];

    /**
     * @SerializationContext(serializeAsObject=true)
     * @Groups({"default"})
     * @SWG\Property(type="object")
     * @var VideoRoomObjectFireplaceDataResponse[]|VideoRoomObjectPortalDataResponse[]
     */
    public array $objectsData = [];

    /**
     * @SerializationContext(serializeAsObject=true)
     * @Groups({"default"})
     * @var VideoRoomConfigObjectResponse[]
     */
    public array $backgroundObjects = [];

    /**
     * @SerializationContext(serializeAsObject=true)
     * @Groups({"default"})
     * @SWG\Property(type="object")
     * @var VideoRoomObjectFireplaceDataResponse[]|VideoRoomObjectPortalDataResponse[]
     */
    public array $backgroundObjectsData = [];

    /**
     * @var string
     */
    public string $dataTrackUrl;

    /**
     * @var float
     * @Groups({"default"})
     */
    public float $imageMemoryMultiplier;

    /**
     * @var bool
     * @Groups({"default"})
     */
    public bool $withSpeakers = false;

    /**
     * @var bool
     * @Groups({"default"})
     */
    public bool $isSystemBackground = false;

    public function __construct()
    {
        $this->dataTrackUrl = $_ENV['DATA_TRUCK_URL'];
    }

    public static function createFromVideoRoomConfig(VideoRoom $videoRoom)
    {
        $videoRoomConfig = $videoRoom->config;
        $self = new self();

        $self->id = $videoRoomConfig->id;
        $self->dataTrackUrl = $videoRoomConfig->dataTrackUrl ?? $_ENV['DATA_TRUCK_URL'];
        $speakerObject = null;
        if ($videoRoomConfig->backgroundRoom) {
            /** @var VideoRoomSpeakerLocationObject|null $speakerObject */
            $speakerObject = $videoRoom->getObjects()->filter(
                fn(VideoRoomObject $videoRoomObject) => $videoRoomObject instanceof VideoRoomSpeakerLocationObject
            )->first();
        }
        $self->speakerLocation = $speakerObject ? $speakerObject->location : new Location();
        $self->backgroundRoomHeightMultiplier = $videoRoomConfig->backgroundRoomHeightMultiplier;
        $self->backgroundRoomWidthMultiplier = $videoRoomConfig->backgroundRoomWidthMultiplier;
        $self->publisherRadarSize = $videoRoomConfig->publisherRadarSize;
        $self->videoQuality = $videoRoomConfig->videoQuality;
        $self->videoBubbleSize = $videoRoomConfig->videoBubbleSize;
        $self->minRoomZoom = $videoRoomConfig->minRoomZoom;
        $self->maxRoomZoom = $videoRoomConfig->maxRoomZoom;
        $self->intervalToSendDataTrackInMilliseconds = $videoRoomConfig->intervalToSendDataTrackInMilliseconds;
        $self->initialRoomScale = $videoRoomConfig->initialRoomScale;
        $self->imageMemoryMultiplier = $videoRoomConfig->imageMemoryMultiplier;

        if ($videoRoomConfig->backgroundRoom) {
            $self->backgroundRoom = new VideoRoomBackgroundResponse($videoRoomConfig->backgroundRoom);

            //phpcs:ignore
            list ($self->backgroundObjects, $self->backgroundObjectsData) = VideoRoomObjectListResponse::getObjectsAndObjectsData(
                $videoRoom->config->backgroundRoom->objects->toArray()
            );

            $self->isSystemBackground = $videoRoomConfig->backgroundRoom->isSystemBackground;
        }

        list ($self->objects, $self->objectsData) = VideoRoomObjectListResponse::getObjectsAndObjectsData(
            $videoRoom->getObjects()->toArray()
        );

        $self->withSpeakers = $videoRoomConfig->withSpeakers;
        return $self;
    }
}
