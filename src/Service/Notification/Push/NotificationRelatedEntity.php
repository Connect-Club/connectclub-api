<?php

namespace App\Service\Notification\Push;

use App\Entity\User;

interface NotificationRelatedEntity
{
    public function calculatePriority(User $user): int;
}
