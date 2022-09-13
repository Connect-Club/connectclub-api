<?php

namespace App\Service\Notification\Push;

use App\Entity\User;
use App\Entity\User\Device;
use App\Service\Notification\Message;

class ReactNativePriorityNotification implements PriorityPushNotification
{
    private PushNotification $decorated;
    private User $initiator;
    private string $type;
    private int $priority;
    private ?string $specificKey;

    public function __construct(
        PushNotification $decorated,
        User $initiator,
        string $type,
        int $priority,
        ?string $specificKey = null
    ) {
        $this->decorated = $decorated;
        $this->initiator = $initiator;
        $this->type = $type;
        $this->priority = $priority;
        $this->specificKey = $specificKey;
    }

    public function supportDevice(Device $device): bool
    {
        return $this->decorated->supportDevice($device);
    }

    public function getMessage(): Message
    {
        return $this->decorated->getMessage();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSpecificKey(): ?string
    {
        return $this->specificKey;
    }

    public function getInitiator(): User
    {
        return $this->initiator;
    }

    public function getPredefinedTranslationParameters(): array
    {
        return $this->decorated->getPredefinedTranslationParameters();
    }
}
