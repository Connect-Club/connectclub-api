<?php

namespace App\Controller\V1;

use App\Client\GoogleCloudStorageClient;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Entity\Photo\UserPhoto;
use App\Entity\User;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Message\CheckAvatarPhotoTheHiveAiMessage;
use App\Repository\Event\EventDraftRepository;
use App\Repository\Photo\UserPhotoRepository;
use App\Repository\VideoChat\BackgroundPhotoRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Security\Voter\VideoRoomVoter;
use App\Service\UserFileUploader;
use App\Swagger\ViewResponse;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Throwable;

/**
 * Class UploadController.
 *
 * @Route("/upload")
 */
class UploadController extends BaseController
{
    private GoogleCloudStorageClient $googleCloudStorageClient;

    /**
     * UploadController constructor.
     */
    public function __construct(GoogleCloudStorageClient $googleCloudStorageClient)
    {
        $this->googleCloudStorageClient = $googleCloudStorageClient;
    }

    /**
     * @SWG\Post(
     *     consumes={"multipart/form-data"},
     *     summary="Upload image",
     *     description="Upload image",
     *     @SWG\Parameter(in="formData", type="file", name="image", description="User photo"),
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="422", description="Fail validation response"),
     *     tags={"Upload"}
     * )
     * @ViewResponse(entityClass=UserPhoto::class, groups={"v1.upload.user_photo"})
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("", methods={"POST"})
     */
    public function uploadImage(
        Request $request,
        UserFileUploader $userFileUploader,
        UserPhotoRepository $userPhotoRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('image') ?? $request->files->get('photo');
        if (!$photo) {
            return $this->createErrorResponse('bad_request', Response::HTTP_BAD_REQUEST);
        }

        $this->prepareAndCheckUserUpload($photo);

        $image = $userFileUploader->uploadUserImage($photo);
        $userPhotoRepository->save($image);

        return $this->handleResponse($image, Response::HTTP_OK, ['v1.upload.user_photo']);
    }

    /**
     * @SWG\Post(
     *     consumes={"multipart/form-data"},
     *     summary="Upload user avatar",
     *     description="Upload user avatar",
     *     @SWG\Parameter(in="formData", type="file", name="photo", description="User photo"),
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="422", description="Fail validation response"),
     *     tags={"Upload"}
     * )
     * @ViewResponse(entityClass=UserPhoto::class, groups={"v1.upload.user_photo"})
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("/user-photo", methods={"POST"})
     */
    public function userPhoto(
        Request $request,
        UserFileUploader $userFileUploader,
        UserPhotoRepository $userPhotoRepository,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var UploadedFile $photo */
        $photo = $request->files->get('photo');
        $this->prepareAndCheckUserUpload($photo);

        $avatar = $userFileUploader->uploadAvatarForUser($photo);
        $userPhotoRepository->save($avatar);

        return $this->handleResponse($avatar, Response::HTTP_OK, ['v1.upload.user_photo']);
    }

    /**
     * @SWG\Post(
     *     consumes={"multipart/form-data"},
     *     summary="Upload video room background for room",
     *     description="Upload video room background for room",
     *     @SWG\Parameter(in="formData", type="file", name="photo", description="Background video chat"),
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="404", description="Video chat room not found"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     @SWG\Response(response="422", description="Fail validation response"),
     *     tags={"Upload", "Video Room"}
     * )
     * @ViewResponse(
     *     entityClass=BackgroundPhoto::class,
     *     groups={"v1.upload.default_photo"},
     *     errorCodesMap={
     *         {Response::HTTP_BAD_REQUEST, "api.v1.background.validate_size_error", "Validation internal error"},
     *         {Response::HTTP_BAD_REQUEST, "api.v1.background.incorrect_height", "Incorrect height"},
     *         {Response::HTTP_BAD_REQUEST, "api.v1.background.incorrect_width", "Incorrect width"},
     *     }
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("/{name}/video-room-background", methods={"POST"})
     */
    public function backgroundVideoRoomPhotoSpecificUser(
        string $name,
        Request $request,
        UserFileUploader $userFileUploader,
        MessageBusInterface $bus,
        BackgroundPhotoRepository $backgroundPhotoRepository,
        VideoRoomRepository $videoRoomRepository,
        EventDraftRepository $eventDraftRepository
    ): JsonResponse {
        $videoRoom = $videoRoomRepository->findOneByName($name);
        if (!$videoRoom || !$this->isGranted(VideoRoomVoter::VIDEO_ROOM_CHANGE_CONFIGURATION, $videoRoom)) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }
        $videoRoom->alwaysReopen = true;

        /** @var UploadedFile $photo */
        $photo = $request->files->get('photo');
        $this->prepareAndCheckUserUpload($photo);

        /** @var User $user */
        $user = $this->getUser();

        if ($videoRoom->draftType) {
            $eventDraft = $eventDraftRepository->findOneBy(['type' => $videoRoom->draftType]);
            if ($eventDraft) {
                $photoPath = $photo->getPath().'/'.$photo->getFilename();
                $imageSizeInfo = getimagesize($photoPath);

                if (!$imageSizeInfo) {
                    return $this->createErrorResponse(
                        'api.v1.background.validate_size_error',
                        Response::HTTP_BAD_REQUEST
                    );
                }

                [$width, $height] = $imageSizeInfo;

                if ($eventDraft->expectedWidth !== null && $eventDraft->expectedWidth != $width) {
                    return $this->createErrorResponse('api.v1.background.incorrect_width', Response::HTTP_BAD_REQUEST);
                }

                if ($eventDraft->expectedHeight !== null && $eventDraft->expectedHeight != $height) {
                    return $this->createErrorResponse('api.v1.background.incorrect_height', Response::HTTP_BAD_REQUEST);
                }
            }
        }

        $videoRoomBackground = $userFileUploader->uploadVideoRoomBackground($user, $photo);
        $backgroundPhotoRepository->save($videoRoomBackground);

        if ($videoRoom->config->backgroundRoom && $videoRoom->config->backgroundRoom->id != $videoRoomBackground->id) {
            $videoRoom->ignoredVideoRoomObjectsIds = []; //Reset ignored video room objects from background
        }
        $videoRoom->config->backgroundRoom = $videoRoomBackground;
        $videoRoomRepository->save($videoRoom);

        $bus->dispatch(new CheckAvatarPhotoTheHiveAiMessage(
            $videoRoomBackground->id,
            $videoRoomBackground->getResizerUrl(800, 800),
            $user->id,
            $videoRoom
        ));

        return $this->handleResponse($videoRoomBackground, Response::HTTP_OK, ['v1.upload.default_photo']);
    }

    /**
     * @SWG\Post(
     *     consumes={"multipart/form-data"},
     *     summary="Upload video room background",
     *     description="Upload video room background",
     *     @SWG\Parameter(in="formData", type="file", name="photo", description="Background video chat"),
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="404", description="Video chat room not found"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     @SWG\Response(response="422", description="Fail validation response"),
     *     tags={"Upload", "Video Room"}
     * )
     * @ViewResponse(entityClass=BackgroundPhoto::class, groups={"v1.upload.default_photo"})
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("/video-room-background", methods={"POST"})
     */
    public function backgroundVideoRoomPhoto(
        Request $request,
        UserFileUploader $userFileUploader,
        BackgroundPhotoRepository $backgroundPhotoRepository
    ) {
        /** @var UploadedFile $photo */
        $photo = $request->files->get('photo');
        $this->prepareAndCheckUserUpload($photo);

        /** @var User $user */
        $user = $this->getUser();

        $videoRoomBackground = $userFileUploader->uploadVideoRoomBackground($user, $photo);
        $backgroundPhotoRepository->save($videoRoomBackground);

        return $this->handleResponse($videoRoomBackground, Response::HTTP_OK, ['v1.upload.default_photo']);
    }

    private function prepareAndCheckUserUpload(?UploadedFile $photo): void
    {
        $constraintFile = new File([
            'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
            'mimeTypesMessage' => 'upload.user_photo.error_mime_type',
        ]);

        $this->unprocessableUnlessValid($photo, [$constraintFile]);
    }
}
