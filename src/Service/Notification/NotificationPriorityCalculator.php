<?php

namespace App\Service\Notification;

use App\Entity\User;
use App\Service\Notification\Push\PriorityPushNotification;

class NotificationPriorityCalculator
{
    public function calculatePriority(User $user, PriorityPushNotification $notification): int
    {
        $type = $notification->getType();
        $specificKey = $notification->getSpecificKey();
        $priority = $notification->getPriority();
        $initiator = $notification->getInitiator();

        if ($priority >= PriorityPushNotification::HIGH) {
            return PriorityPushNotification::HIGH;
        }

        $uniqueNotificationType = $this->generateUniqueNotificationType($type, $specificKey);

        return PriorityPushNotification::LOW;
    }

    private function generateUniqueNotificationType(string $type, ?string $specificKey): string
    {
        return $type . '_' . $specificKey;
    }
}
