<?php

namespace App\Service\SMS;

use App\Entity\User\SmsVerification;
use App\Repository\User\DeviceRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\ReactNativePushNotification;
use libphonenumber\PhoneNumber;
use LogicException;

class PushNotificationSmsProvider implements SmsProviderInterface
{
    private DeviceRepository $deviceRepository;
    private UserRepository $userRepository;
    private NotificationManager $notificationManager;

    public function __construct(
        DeviceRepository $deviceRepository,
        UserRepository $userRepository,
        NotificationManager $notificationManager
    ) {
        $this->deviceRepository = $deviceRepository;
        $this->userRepository = $userRepository;
        $this->notificationManager = $notificationManager;
    }

    public function getProviderCode(): string
    {
        return 'push';
    }

    public function supportPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        $user = $this->userRepository->findOneBy(['phone' => $phoneNumber]);
        if (!$user || !$user->isTesterAuthorizationPushes) {
            return false;
        }

        $device = $this->deviceRepository->findOneBy(['user' => $user]);

        return $device !== null;
    }

    public function sendVerificationCode(SmsVerification $smsVerification, PhoneNumber $phoneNumber): string
    {
        $user = $this->userRepository->findOneBy(['phone' => $phoneNumber]);
        if (!$user) {
            throw new LogicException('User not found with phone number');
        }

        $smsVerification->code = $code = (string) mt_rand(1000, 9999);

        $this->notificationManager->sendNotifications(
            $user,
            new ReactNativePushNotification(
                'authorization-code',
                'Authorization code '.$code,
                'Authorization code '.$code
            )
        );

        return (string) $code;
    }

    public function checkVerificationCode(SmsVerification $smsVerification, string $code): bool
    {
        return $smsVerification->code == $code;
    }
}
