<?php

namespace App\Message;

use App\DTO\V1\User\LoadPhoneContactsRequest;
use Symfony\Component\Lock\Key;

final class UploadContactMessage
{
    private int $userId;
    private LoadPhoneContactsRequest $loadPhoneContactsRequest;
    private string $calculatedChangesHash;

    public function __construct(
        int $userId,
        LoadPhoneContactsRequest $loadPhoneContactsRequest,
        string $calculatedChangesHash
    ) {
        $this->userId = $userId;
        $this->loadPhoneContactsRequest = $loadPhoneContactsRequest;
        $this->calculatedChangesHash = $calculatedChangesHash;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getLoadPhoneContactsRequest(): LoadPhoneContactsRequest
    {
        return $this->loadPhoneContactsRequest;
    }

    public function getCalculatedChangesHash(): string
    {
        return $this->calculatedChangesHash;
    }
}
