<?php

namespace App\Service\Notification\Push;

use App\Entity\User\Device;
use App\Service\Notification\Message;

class IOSPushNotification implements PushNotification
{
    private ?string $message;
    private array $options;

    public function __construct(?string $title, ?string $message, array $options = [])
    {
        $this->message = $message;
        $this->options = $options;

        if ($title) {
            $this->options['title'] = $title;
        }

        $this->options['custom']['analytics'] ??= 'remote_push_'.($options['custom']['type'] ?? null);
        $this->options['sound'] = $options['sound'] ?? 'default';
    }

    public function supportDevice(Device $device): bool
    {
        return $device->type == Device::TYPE_IOS;
    }

    public function getMessage(): Message
    {
        return new Message($this->message, $this->options);
    }

    public function getPredefinedTranslationParameters(): array
    {
        return [];
    }
}
