<?php

namespace App\Service\Notification\Push;

use App\Entity\User\Device;
use App\Service\Notification\Message;

class AndroidPushNotification implements PushNotification
{
    private array $notificationBody;

    public function __construct(array $notificationBody)
    {
        $this->notificationBody = $notificationBody;
        $this->notificationBody['notificationData'] ??= [];
        $this->notificationBody['notificationData']['custom'] ??= [];

        $this->notificationBody['notificationData']['custom']['analytics'] ??= 'remote_push_'.(
            $notificationBody['notificationData']['type'] ?? null
        );
    }

    public function supportDevice(Device $device): bool
    {
        return $device->type == Device::TYPE_ANDROID;
    }

    public function getMessage(): Message
    {
        return new Message(null, $this->notificationBody);
    }

    public function getPredefinedTranslationParameters(): array
    {
        return [];
    }
}
