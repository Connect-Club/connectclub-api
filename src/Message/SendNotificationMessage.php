<?php

namespace App\Message;

use App\Entity\Notification\Notification;

class SendNotificationMessage
{
    public ?string $notificationId = null;
    public string $pushToken;
    public string $platformType;
    public ?string $message;
    public ?Notification $notificationEntity = null;
    public array $options;

    public function __construct(
        Notification $notification,
        string $pushToken,
        string $platformType,
        ?string $message,
        array $options = []
    ) {
        $this->notificationEntity = $notification;
        $this->pushToken = $pushToken;
        $this->platformType = $platformType;
        $this->message = $message;
        $this->options = $options;
    }

    public function idempotentKey(): string
    {
        if ($this->notificationId) {
            return sha1($this->notificationId);
        }

        if ($this->notificationEntity) {
            $key = serialize($this->notificationEntity->messageParameters);
            $key .= $this->notificationEntity->message;
            $key .= $this->pushToken;
            $key .= $this->platformType;

            return sha1($key);
        }

        return sha1(serialize($this));
    }
}
