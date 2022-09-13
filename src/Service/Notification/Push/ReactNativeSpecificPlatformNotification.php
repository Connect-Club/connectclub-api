<?php

namespace App\Service\Notification\Push;

use App\Entity\User;
use App\Entity\User\Device;
use App\Service\Notification\Message;

class ReactNativeSpecificPlatformNotification implements PushNotification
{
    private PushNotification $decorated;
    private string $supportDeviceType;

    public function __construct(ReactNativePushNotification $decorated, string $supportDeviceType)
    {
        $this->decorated = $decorated;
        $this->supportDeviceType = $supportDeviceType;
    }

    public function supportDevice(Device $device): bool
    {
        return $device->type == $this->supportDeviceType;
    }

    public function getMessage(): Message
    {
        return $this->decorated->getMessage();
    }

    public function getPredefinedTranslationParameters(): array
    {
        return $this->decorated->getPredefinedTranslationParameters();
    }
}
