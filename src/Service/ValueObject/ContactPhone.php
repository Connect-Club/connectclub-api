<?php

namespace App\Service\ValueObject;

class ContactPhone
{
    /** @var string */
    public string $fullName;

    /** @var string[] */
    public array $phoneNumbers;

    /** @var string|null */
    public ?string $thumbnail = null;

    public function __construct(string $fullName, array $phoneNumbers, ?string $thumbnail = null)
    {
        $this->fullName = $fullName;
        $this->phoneNumbers = $phoneNumbers;
        $this->thumbnail = $thumbnail;
    }
}
