<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\PostDeviceRequest;
use App\Entity\Activity\WelcomeOnBoardingFriendActivity;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventDraft;
use App\Entity\User;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\User\DeviceRepository;
use App\Repository\User\PhoneContactRepository;
use App\Service\ActivityManager;
use App\Service\EventLogManager;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\VideoRoomManager;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DeviceController.
 *
 * @Route("/device")
 */
class DeviceController extends BaseController
{
    private DeviceRepository $deviceRepository;
    private EventLogManager $eventLogManager;

    public function __construct(DeviceRepository $deviceRepository, EventLogManager $eventLogManager)
    {
        $this->deviceRepository = $deviceRepository;
        $this->eventLogManager = $eventLogManager;
    }

    /**
     * @SWG\Delete (
     *     @SWG\Response(response="200", description="Success create new device or update exists device"),
     *     description="Remove invalid device hook",
     *     summary="Remove invalid device hook",
     *     tags={"Device"}
     * )
     * @ViewResponse()
     * @Route("/{deviceToken}", methods={"DELETE"})
     */
    public function remove(string $deviceToken, LoggerInterface $logger, MessageBusInterface $bus): JsonResponse
    {
        if (!$device = $this->deviceRepository->findOneBy(['token' => $deviceToken])) {
            $bus->dispatch(new AmplitudeEventStatisticsMessage('push_send_fail', [], null, $deviceToken));

            $logger->warning('Not found device', ['token' => $deviceToken]);

            return $this->handleResponse([]);
        }

        $this->eventLogManager->logEventCustomObject(
            'remove_device',
            'device',
            $device->id,
            ['token' => $deviceToken, 'userId' => $device->user->id]
        );

        $bus->dispatch(new AmplitudeEventStatisticsMessage('push_send_fail', [], $device->user, $device->id));
        $this->deviceRepository->remove($device);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     @SWG\Response(response="200", description="Success create new device or update exists device"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         schema=@SWG\Schema(
     *             ref=@Nelmio\Model(type=PostDeviceRequest::class)
     *         )
     *     ),
     *     description="Add new device",
     *     summary="Add new device",
     *     tags={"Device"}
     * )
     * @ViewResponse()
     * @Route("", methods={"POST"})
     */
    public function device(
        Request $request,
        EntityManagerInterface $entityManager,
        EventLogManager $eventLogManager
    ): JsonResponse {
        /** @var PostDeviceRequest $postDeviceRequest */
        $postDeviceRequest = $this->getEntityFromRequestTo($request, PostDeviceRequest::class);

        $type = $this->isGranted('ROLE_ANDROID') ? User\Device::TYPE_ANDROID : User\Device::TYPE_IOS;
        if ($postDeviceRequest->type) {
            if (!in_array($postDeviceRequest->type, [User\Device::TYPE_ANDROID_REACT, User\Device::TYPE_IOS_REACT])) {
                return $this->createErrorResponse([ErrorCode::V1_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
            }

            $type = $postDeviceRequest->type;
        }

        $user = $this->getUser();
        $userId = $user->getId();

        $deviceId = $userId.'_'.$postDeviceRequest->deviceId;
        $pushToken = $postDeviceRequest->pushToken;
        $model = $postDeviceRequest->model;

        if ($pushToken) {
            $device = $this->deviceRepository->findDeviceByIdOrPushToken($deviceId, $pushToken);
        } else {
            //Maybe device id registered on another user
            if (!$device = $this->deviceRepository->find($deviceId)) {
                $device = $this->deviceRepository->findDeviceForUserWithModelName($user, $model);
            }
        }

        if ($device) {
            if ($device->user->id != $userId && $device->token == $postDeviceRequest->pushToken) {
                $eventLogManager->logEvent($device, 'register_device_another_user', ['user' => $user->id]);
                $this->deviceRepository->remove($device);
                $device = null;
            } else {
                $device->id = $deviceId;
                $device->user = $user;
                $device->token = $postDeviceRequest->pushToken;
                $device->model = $postDeviceRequest->model;
                $device->locale = $postDeviceRequest->locale;
                $device->timeZone = (string) $postDeviceRequest->timeZone;
                $eventLogManager->logEvent($device, 'update_device_data', ['user' => $user->id]);
            }
        }

        if (!$device) {
            $device = new User\Device(
                $deviceId,
                $user,
                $type,
                $postDeviceRequest->pushToken,
                (string) $postDeviceRequest->timeZone,
                $postDeviceRequest->locale,
                $postDeviceRequest->model
            );

            $eventLogManager->logEvent($device, 'create_new_device', ['user' => $user->id]);
        }

        $this->deviceRepository->save($device);

        $entityManager->flush();

        return $this->handleResponse([]);
    }
}
