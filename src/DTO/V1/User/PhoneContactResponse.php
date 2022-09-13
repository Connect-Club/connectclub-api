<?php

namespace App\DTO\V1\User;

use libphonenumber\PhoneNumber;

class PhoneContactResponse
{
    public PhoneNumber $phone;
    /** @var string[] */
    public array $phones;
    /** @var PhoneContactNumberResponse[] */
    public array $additionalPhones = [];
    public string $displayName;
    public ?string $thumbnail = null;
    public string $status;
    public int $countInAnotherUsers;

    public function __construct(
        array $phones,
        array $additionalPhones,
        string $displayName,
        string $status,
        int $countInAnotherUsers,
        ?PhoneNumber $defaultPhone = null,
        ?string $thumbnail = null
    ) {
        $this->phone = $defaultPhone ?? $phones[0];
        $this->additionalPhones = $additionalPhones;
        $this->phones = $phones ?: ($defaultPhone ? [$defaultPhone] : []);
        $this->displayName = $displayName;
        $this->status = $status;
        $this->countInAnotherUsers = $countInAnotherUsers;
        $this->thumbnail = $thumbnail;
    }
}
