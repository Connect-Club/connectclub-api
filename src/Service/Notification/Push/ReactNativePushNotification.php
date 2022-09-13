<?php

namespace App\Service\Notification\Push;

use App\Entity\User\Device;
use App\Service\Notification\Message;

class ReactNativePushNotification implements PushNotification
{
    private ?string $message;
    private array $options;
    private array $predefinedTranslationParameters = [];

    public function __construct(
        string $type,
        ?string $title,
        ?string $message,
        array $options = [],
        $predefinedTranslationParameters = []
    ) {
        $this->message = $message;
        $this->options = $options;
        $this->options['type'] = $type;
        if ($title) {
            $this->options['title'] = $title;
        }
        $this->predefinedTranslationParameters = $predefinedTranslationParameters;
    }

    public function supportDevice(Device $device): bool
    {
        return in_array($device->type, [Device::TYPE_ANDROID_REACT, Device::TYPE_IOS_REACT]);
    }

    public function getMessage(): Message
    {
        return new Message($this->message, $this->options);
    }

    public function getPredefinedTranslationParameters(): array
    {
        return $this->predefinedTranslationParameters;
    }
}
