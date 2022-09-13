<?php

namespace App\DTO\V1\User;

class ContactPhoneRequest
{
    /** @var string */
    public string $fullName;

    /** @var string */
    public ?string $phoneNumber = null;

    /** @var string[] */
    public array $phoneNumbers = [];

    /** @var string|null */
    public ?string $thumbnail = null;
}
