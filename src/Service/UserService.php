<?php

namespace App\Service;

use App\Entity\User;

class UserService
{
    private PhoneNumberManager $phoneNumberManager;
    private bool $isStage;

    public function __construct(PhoneNumberManager $phoneNumberManager, string $isStage)
    {
        $this->phoneNumberManager = $phoneNumberManager;
        $this->isStage = $isStage === '1';
    }

    public function isTester(User $user): bool
    {
        if ($user->isTester) {
            return true;
        }

        if (!$user->phone) {
            return false;
        }

        return $this->phoneNumberManager->isTestPhone($user->phone)
            && !$this->isStage;
    }
}
