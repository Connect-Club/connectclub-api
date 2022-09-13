<?php

namespace App\Message;

use App\Service\ValueObject\ContactPhone;

class UploadPhoneContactsMessage
{
    private int $userId;
    private array $contacts;

    public function __construct(int $userId, array $contacts)
    {
        $this->userId = $userId;
        $this->contacts = $contacts;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    /** @return ContactPhone[] */
    public function getContacts(): array
    {
        return $this->contacts;
    }
}
