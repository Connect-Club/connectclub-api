<?php

namespace App\Message;

use App\Entity\User;

class UploadUserToElasticsearchMessage
{
    private int $userId;

    public function __construct(User $user)
    {
        $this->userId = (int) $user->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
