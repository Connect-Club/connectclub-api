<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\ShareScreenObject;
use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use App\Entity\VideoChat\Object\VideoRoomImageObject;
use App\Entity\VideoChat\Object\VideoRoomImageZoneObject;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use App\Entity\VideoChat\Object\VideoRoomObjectTimeBox;
use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use App\Entity\VideoChat\Object\VideoRoomSquarePortalObject;
use App\Entity\VideoChat\Object\VideoRoomStaticObject;
use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use App\Entity\VideoChat\VideoRoomObject;
use App\Entity\VideoChatObject\QuietZoneObject;

class VideoRoomObjectListResponse
{
    /**
     * @param VideoRoomObject[] $objects
     * @return array[]
     */
    public static function getObjectsAndObjectsData(array $objects) : array
    {
        $responseObjects = $responseObjectsData = [];

        foreach ($objects as $videoRoomObject) {
            $id = $videoRoomObject->id;

            $type = '';
            $objectData = new \stdClass();
            switch (get_class($videoRoomObject)) {
                case VideoRoomPortalObject::class:
                    $objectData = VideoRoomObjectPortalDataResponse::createFromObject($videoRoomObject);
                    $type = VideoRoomObject::TYPE_PORTAL;
                    break;
                case VideoRoomFireplaceObject::class:
                    $objectData = VideoRoomObjectFireplaceDataResponse::createFromObject($videoRoomObject);
                    $type = VideoRoomObject::TYPE_FIREPLACE;
                    break;
                case VideoRoomMainSpawnObject::class:
                    $type = VideoRoomObject::TYPE_MAIN_SPAWN;
                    break;
                case QuietZoneObject::class:
                    $objectData = VideoRoomObjectQuietZoneDataResponse::createFromObject($videoRoomObject);
                    $type = VideoRoomObject::TYPE_QUIET_ZONE;
                    break;
                case VideoRoomSpeakerLocationObject::class:
                    $type = VideoRoomObject::TYPE_SPEAKER_LOCATION;
                    break;
                case VideoRoomStaticObject::class:
                    $type = VideoRoomObject::TYPE_STATIC_OBJECT;
                    break;
                case VideoRoomVideoObject::class:
                    $objectData = new VideoRoomObjectVideoDataResponse($videoRoomObject);
                    $type = VideoRoomObject::TYPE_VIDEO;
                    break;
                case VideoRoomSquarePortalObject::class:
                    /** @var VideoRoomSquarePortalObject $videoRoomObject */
                    $objectData = new VideoRoomObjectSquareDataResponse($videoRoomObject->name);
                    $type = VideoRoomObject::TYPE_SQUARE_PORTAL;
                    break;
                case ShareScreenObject::class:
                    $type = VideoRoomObject::TYPE_SHARE_SCREEN;
                    break;
                case VideoRoomImageZoneObject::class:
                    $type = VideoRoomObject::TYPE_IMAGE_ZONE;
                    break;
                case VideoRoomImageObject::class:
                    if ($videoRoomObject instanceof VideoRoomImageObject) {
                        $objectData = new VideoRoomObjectImageDataResponse($videoRoomObject);
                    }

                    $type = VideoRoomObject::TYPE_IMAGE;
                    break;
                case VideoRoomObjectTimeBox::class:
                    $type = VideoRoomObject::TYPE_TIME_BOX;
                    break;
                case VideoRoomNftImageObject::class:
                    $objectData = new VideoRoomObjectNftImageDataResponse($videoRoomObject);
                    $type = VideoRoomObject::TYPE_NFT_IMAGE;
                    break;
            }

            $responseObjects[$id] = VideoRoomConfigObjectResponse::createFromObject($type, $videoRoomObject);
            $responseObjectsData[$id] = $objectData;
        }

        return [$responseObjects, $responseObjectsData];
    }
}
