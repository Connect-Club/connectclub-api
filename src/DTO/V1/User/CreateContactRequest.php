<?php

namespace App\DTO\V1\User;

class CreateContactRequest
{
    /** @var string|null */
    public ?string $roomSid = null;

    /** @var int|null */
    public ?int $groupChatId = null;
}
