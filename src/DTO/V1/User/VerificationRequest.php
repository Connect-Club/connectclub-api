<?php

namespace App\DTO\V1\User;

use App\Validator\PhoneNumber;

class VerificationRequest
{
    /** @PhoneNumber(type="mobile", message="not_valid_mobile_phone_number") */
    public string $phone;
}
