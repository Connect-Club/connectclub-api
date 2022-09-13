<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\VideoRoom\VideoRoomUploadObjectResponse;
use App\Entity\Ethereum\UserToken;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\Object\ShareScreenObject;
use App\Entity\VideoChat\Object\VideoRoomImageObject;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use App\Entity\VideoChat\Object\VideoRoomObjectTimeBox;
use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use App\Entity\VideoChat\Object\VideoRoomStaticObject;
use App\Entity\VideoChat\VideoRoomObject;
use App\Entity\VideoChatObject\QuietZoneObject;
use App\Exception\EntityNotFoundException;
use App\Message\CheckAvatarPhotoTheHiveAiMessage;
use App\Repository\Ethereum\UserTokenRepository;
use App\Repository\Photo\VideoRoomImageObjectPhotoRepository;
use App\Repository\VideoChat\BackgroundPhotoRepository;
use App\Repository\VideoChat\VideoRoomObjectRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Security\Voter\VideoRoomVoter;
use App\Service\UserFileUploader;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\File;
use Throwable;

/**
 * @Route("/video-room-object")
 */
class VideoRoomObjectController extends BaseController
{
    /** @var Serializer */
    protected SerializerInterface $serializer;
    private VideoRoomRepository $videoRoomRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(VideoRoomRepository $videoRoomRepository, EntityManagerInterface $entityManager)
    {
        $this->videoRoomRepository = $videoRoomRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @SWG\Post(
     *     consumes={"multipart/form-data"},
     *     summary="Upload video room image object",
     *     description="Upload video room image object",
     *     @SWG\Parameter(in="formData", type="file", name="photo", description="Image object (png only)"),
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="422", description="Fail validation response"),
     *     tags={"Upload", "Video Room"},
     * )
     * @ViewResponse(entityClass=VideoRoomUploadObjectResponse::class)
     *
     * @Route("/upload/image", methods={"POST"})
     */
    public function uploadVideoRoomImage(
        Request $request,
        UserFileUploader $uploader,
        VideoRoomImageObjectPhotoRepository $videoRoomImageObjectPhotoRepository,
        VideoRoomObjectRepository $videoRoomObjectRepository
    ) {
        /** @var ?UploadedFile $photo */
        $photo = $request->files->get('photo');
        if (!$photo) {
            return $this->handleResponse([ErrorCode::V1_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        $this->unprocessableUnlessValid($photo, [new File([
            'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
            'mimeTypesMessage' => 'upload.user_photo.error_mime_type',
        ])]);

        $videoRoomImageObjectPhoto = $uploader->uploadVideoRoomImageObject($photo);
        $videoRoomImageObjectPhotoRepository->save($videoRoomImageObjectPhoto);

        $videoRoomImageObject = new VideoRoomImageObject(null, null, new Location(), 0, 0);
        $videoRoomImageObject->photo = $videoRoomImageObjectPhoto;
        $videoRoomObjectRepository->save($videoRoomImageObject);

        return $this->handleResponse(
            new VideoRoomUploadObjectResponse($videoRoomImageObject, VideoRoomObject::TYPE_IMAGE),
            Response::HTTP_OK
        );
    }

    /**
     * @SWG\Patch(
     *     description="Update objects in video room",
     *     summary="Update objects in video room",
     *     tags={"Video Room"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         schema=@SWG\Schema(example={
     *             {
     *                 "type": "fireplace",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "radius": 25.50,
     *                 "lottieSrc": "fireplace",
     *                 "soundSrc": "fireplace",
     *             },
     *             {
     *                 "type": "main_spawn",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "video",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "radius": 25.50,
     *                 "length": 100,
     *                 "videoSrc": "asdsad",
     *             },
     *             {
     *                 "type": "speaker_location",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "static_object",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "image",
     *                 "id": 1,
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "quiet_zone",
     *                 "location": {"x": 100, "y": 100},
     *                 "radius": 100,
     *                 "width": 100,
     *                 "height": 100,
     *             }
     *         })
     *     )
     * )
     * @ViewResponse()
     * @Route("/video-room/{videoRoomName}", methods={"PATCH"})
     */
    public function patchVideoRoom(Request $request, MessageBusInterface $bus, string $videoRoomName): JsonResponse
    {
        $currentUser = $this->getUser();
        $videoRoom = $this->videoRoomRepository->findOneByName($videoRoomName);
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$videoRoom) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(VideoRoomVoter::VIDEO_ROOM_CHANGE_CONFIGURATION, $videoRoom)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if (!$isAdmin && !$videoRoom->community->owner->equals($currentUser)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        $videoRoom->alwaysReopen = true;
        $videoRoom->objects->clear();
        $newObjects = json_decode($request->getContent(), true);

        $videoRoomImageObjects = [];

        $limits = [
            VideoRoomMainSpawnObject::class => 1,
            ShareScreenObject::class => 1,
            VideoRoomSpeakerLocationObject::class => 100,
            QuietZoneObject::class => 1,
            VideoRoomObjectTimeBox::class => 1,
            VideoRoomImageObject::class => 1000,
            VideoRoomStaticObject::class => 1000,
        ];

        $actualVideoRoomObjects = $videoRoom->objects->toArray();
        $actualBackgroundRoomObjects = [];
        if ($videoRoom->config->backgroundRoom) {
            $actualBackgroundRoomObjects = $videoRoom->config->backgroundRoom->objects->toArray();
        }

        try {
            $videoRoomObjects = $this->getObjects($newObjects);

            foreach ($limits as $limitClass => $limit) {
                $count = 0;

                foreach ($videoRoomObjects as $videoRoomObject) {
                    if (get_class($videoRoomObject) === $limitClass) {
                        ++$count;
                    }
                }

                if ($count > $limit) {
                    return $this->createErrorResponse('api.background.'.mb_strtolower($limitClass).'.limit');
                }
            }

            foreach ($actualBackgroundRoomObjects as $actualBackgroundRoomObject) {
                $found = false;
                foreach ($videoRoomObjects as $videoRoomObject) {
                    if ($videoRoomObject->id == $actualBackgroundRoomObject->id) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $videoRoom->ignoreBackgroundObject($actualBackgroundRoomObject);
                }
            }


            foreach ($videoRoomObjects as $videoRoomObject) {
                if (!$isAdmin && !in_array(get_class($videoRoomObject), array_keys($limits))) {
                    continue;
                }

                $isNewObject = $videoRoomObject->id === null;
                $isFromRoomObject = false;
                $isFromBackgroundObject = false;
                $isChangedObject = false;

                if (!$isNewObject) {
                    foreach ($actualVideoRoomObjects as $actualVideoRoomObject) {
                        if ($actualVideoRoomObject->id == $videoRoomObject->id) {
                            $isFromRoomObject = true;
                            $isChangedObject = $videoRoomObject->isChangedFrom($actualVideoRoomObject);
                            break;
                        }
                    }

                    foreach ($actualBackgroundRoomObjects as $actualBackgroundRoomObject) {
                        if ($actualBackgroundRoomObject->id == $videoRoomObject->id) {
                            $isFromBackgroundObject = true;
                            //Reset doctrine reference $actualBackgroundRoomObject to $videoRoomObject
                            $clonedVideoRoomObject = unserialize(serialize($videoRoomObject));
                            $clonedVideoRoomObject->id = $videoRoomObject->id;
                            //Reset populates changes from request from $videoRoomObject
                            $this->entityManager->refresh($videoRoomObject);
                            //Check if background video room object has changed right now
                            $isChangedObject = $videoRoomObject->isChangedFrom(
                                $clonedVideoRoomObject
                            );

                            $videoRoomObject = $clonedVideoRoomObject;
                            break;
                        }
                    }
                }

                if ($isFromBackgroundObject) {
                    if (!$isChangedObject) {
                        continue;
                    }

                    $videoRoom->ignoreBackgroundObject($videoRoomObject);

                    //And fork it for specific video room
                    $videoRoomObject = clone $videoRoomObject;
                    $videoRoomObject->background = null;
                }

                $videoRoom->objects->add($videoRoomObject);
                $videoRoomObject->videoRoom = $videoRoom;

                if ($videoRoomObject instanceof VideoRoomImageObject) {
                    $videoRoomImageObjects[] = $videoRoomObject;
                }
            }
        } catch (NotNormalizableValueException $exception) {
            return $this->createErrorResponse([ErrorCode::V1_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        } catch (EntityNotFoundException $entityNotFoundException) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_OBJECT_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        try {
            foreach ($videoRoomImageObjects as $videoRoomImageObject) {
                $bus->dispatch(new CheckAvatarPhotoTheHiveAiMessage(
                    $videoRoomImageObject->photo->id,
                    $videoRoomImageObject->photo->getOriginalUrl(),
                    $currentUser->id,
                    $videoRoom
                ));
            }
        } catch (Throwable $exception) {
        }

        $this->entityManager->flush();

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Patch(
     *     description="Update objects in video room",
     *     summary="Update objects in video room",
     *     tags={"Video Room"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="403", description="OK"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         schema=@SWG\Schema(example={
     *             {
     *                 "type": "fireplace",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "radius": 25.50,
     *                 "lottieSrc": "fireplace",
     *                 "soundSrc": "fireplace",
     *             },
     *             {
     *                 "type": "main_spawn",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "video",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "radius": 25.50,
     *                 "length": 100,
     *                 "videoSrc": "asdsad",
     *             },
     *             {
     *                 "type": "speaker_location",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "static_object",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "image",
     *                 "id": 1,
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             }
     *         })
     *     )
     * )
     * @ViewResponse()
     * @Route("/background/{backgroundId}", methods={"PATCH"}, requirements={"backgroundId": "\d+"})
     */
    public function patchBackground(
        Request $request,
        int $backgroundId,
        BackgroundPhotoRepository $backgroundPhotoRepository
    ) {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if (!$background = $backgroundPhotoRepository->find($backgroundId)) {
            return $this->createErrorResponse(
                [ErrorCode::V1_VIDEO_ROOM_BACKGROUND_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        $background->objects->clear();
        $newObjects = json_decode($request->getContent(), true);

        try {
            $videoRoomObjects = $this->getObjects($newObjects);

            foreach ($videoRoomObjects as $videoRoomObject) {
                $videoRoomObject->background = $background;
                $background->objects->add($videoRoomObject);
            }
        } catch (NotNormalizableValueException $exception) {
            return $this->createErrorResponse([ErrorCode::V1_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        } catch (EntityNotFoundException $entityNotFoundException) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_OBJECT_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $backgroundPhotoRepository->save($background);

        return $this->handleResponse([]);
    }

    /** @return VideoRoomObject[] */
    private function getObjects(array $newObjects): array
    {
        $objects = [];
        $videoRoomObjectMap = $this->entityManager->getClassMetadata(VideoRoomObject::class)->discriminatorMap;

        array_walk($newObjects, fn (&$v, $k) => array_walk($v, fn (&$v, $k) => $v = $k == 'radius' ? (float)$v : $v));

        foreach ($newObjects as $i => $objectData) {
            if (!isset($objectData['type'])) {
                continue;
            }

            if (!isset($videoRoomObjectMap[$objectData['type']])) {
                continue;
            }

            $objectEntityClass = $videoRoomObjectMap[$objectData['type']];

            if (isset($objectData['id'])) {
                $objectId = (int) $objectData['id'];

                if (!$entity = $this->entityManager->getRepository($objectEntityClass)->find($objectId)) {
                    throw new EntityNotFoundException($objectEntityClass, $objectId);
                }

                $objects[] = $this->serializer->denormalize($objectData, $objectEntityClass, 'json', [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $entity,
                ]);
            } else {
                $objects[] = $this->serializer->denormalize($objectData, $objectEntityClass, null);
            }
        }

        return $objects;
    }

    /**
     * @SWG\Post(
     *     summary="Create video room nft image object",
     *     description="Create video room nft image object",
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="404", description="user token not found"),
     *     tags={"Video Room", "Nft Image"},
     * )
     * @ViewResponse(entityClass=VideoRoomUploadObjectResponse::class)
     *
     * @Route("/token/{id}/image", methods={"POST"})
     */
    public function createTokenImage(
        string $id,
        UserTokenRepository $userTokenRepository
    ) {
        $currentUser = $this->getUser();

        /** @var UserToken|null $userToken */
        $userToken = $userTokenRepository->findOneBy([
            'user' => $currentUser->getId(),
            'tokenId' => $id,
        ]);

        if (!$userToken) {
            return $this->handleResponse([ErrorCode::V1_VIDEO_ROOM_OBJECT_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $videoRoomNftImageObject = new VideoRoomNftImageObject(null, null, new Location(), 0, 0);
        $videoRoomNftImageObject->photo = $userToken->nftImage;
        $userTokenRepository->save($videoRoomNftImageObject);

        return $this->handleResponse(
            new VideoRoomUploadObjectResponse($videoRoomNftImageObject, VideoRoomObject::TYPE_NFT_IMAGE),
        );
    }
}
