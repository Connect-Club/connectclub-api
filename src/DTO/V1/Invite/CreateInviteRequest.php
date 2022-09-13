<?php

namespace App\DTO\V1\Invite;

use App\Validator\PhoneNumber;

class CreateInviteRequest
{
    /** @PhoneNumber(type="mobile", message="not_valid_mobile_phone_number") */
    public string $phone;
}
