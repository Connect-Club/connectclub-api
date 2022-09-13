<?php

namespace App\DTO\V1\User;

class PhoneContactNumberResponse
{
    /** @var string */
    public string $phone;

    /** @var string */
    public string $status;

    public function __construct(string $phone, string $status)
    {
        $this->phone = $phone;
        $this->status = $status;
    }
}
