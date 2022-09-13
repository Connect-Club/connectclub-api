<?php

namespace App\Service\Notification\Push;

use App\Entity\User;

interface PriorityPushNotification extends PushNotification
{
    const LOW = 1;
    const MIDDLE = 2;
    const HIGH = 3;

    public function getType(): string;
    public function getPriority(): int;
    public function getSpecificKey(): ?string;
    public function getInitiator(): User;
}
