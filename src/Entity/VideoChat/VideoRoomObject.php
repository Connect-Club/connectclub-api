<?php

namespace App\Entity\VideoChat;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use App\Entity\VideoChat\Object\VideoRoomSquarePortalObject;
use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use App\Entity\VideoChat\Object\VideoRoomStaticObject;
use App\Entity\VideoChat\Object\VideoRoomImageObject;
use App\Entity\VideoChat\Object\ShareScreenObject;
use App\Entity\VideoChat\Object\VideoRoomObjectTimeBox;
use App\Entity\VideoChat\Object\VideoRoomImageZoneObject;
use App\Entity\VideoChatObject\QuietZoneObject;

/**
 * @ORM\Entity()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     VideoRoomObject::TYPE_PORTAL = VideoRoomPortalObject::class,
 *     VideoRoomObject::TYPE_MAIN_SPAWN = VideoRoomMainSpawnObject::class,
 *     VideoRoomObject::TYPE_FIREPLACE = VideoRoomFireplaceObject::class,
 *     VideoRoomObject::TYPE_VIDEO = VideoRoomVideoObject::class,
 *     VideoRoomObject::TYPE_SQUARE_PORTAL = VideoRoomSquarePortalObject::class,
 *     VideoRoomObject::TYPE_SPEAKER_LOCATION = VideoRoomSpeakerLocationObject::class,
 *     VideoRoomObject::TYPE_STATIC_OBJECT = VideoRoomStaticObject::class,
 *     VideoRoomObject::TYPE_IMAGE = VideoRoomImageObject::class,
 *     VideoRoomObject::TYPE_NFT_IMAGE = VideoRoomNftImageObject::class,
 *     VideoRoomObject::TYPE_IMAGE_ZONE = VideoRoomImageZoneObject::class,
 *     VideoRoomObject::TYPE_SHARE_SCREEN = ShareScreenObject::class,
 *     VideoRoomObject::TYPE_TIME_BOX = VideoRoomObjectTimeBox::class,
 *     VideoRoomObject::TYPE_QUIET_ZONE = QuietZoneObject::class,
 * })
 */
abstract class VideoRoomObject
{
    const TYPE_PORTAL = 'portal';
    const TYPE_MAIN_SPAWN = 'main_spawn';
    const TYPE_FIREPLACE = 'fireplace';
    const TYPE_VIDEO = 'video';
    const TYPE_SQUARE_PORTAL = 'square_portal';
    const TYPE_SPEAKER_LOCATION = 'speaker_location';
    const TYPE_STATIC_OBJECT = 'static_object';
    const TYPE_IMAGE = 'image';
    const TYPE_NFT_IMAGE = 'nft_image';
    const TYPE_IMAGE_ZONE = 'image_zone';
    const TYPE_SHARE_SCREEN = 'share_screen';
    const TYPE_TIME_BOX = 'time_box';
    const TYPE_QUIET_ZONE = 'quiet_zone';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\Embedded(Location::class, columnPrefix="position_") */
    public Location $location;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\BackgroundPhoto", inversedBy="objects") */
    public ?BackgroundPhoto $background;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom", inversedBy="objects") */
    public ?VideoRoom $videoRoom;

    /** @ORM\Column(type="integer") */
    public int $width;

    /** @ORM\Column(type="integer") */
    public int $height;

    public function __construct(
        ?VideoRoom $videoRoom,
        ?BackgroundPhoto $background,
        Location $location,
        int $width,
        int $height
    ) {
        $this->videoRoom = $videoRoom;
        $this->location = $location;
        $this->background = $background;
        $this->width = $width;
        $this->height = $height;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isChangedFrom(VideoRoomObject $videoRoomObject): bool
    {
        return $this->location->x !== $videoRoomObject->location->x ||
               $this->location->y !== $videoRoomObject->location->y ||
               $this->height !== $videoRoomObject->height ||
               $this->width !== $videoRoomObject->width;
    }

    public function __clone()
    {
        $this->id = null;
    }
}
