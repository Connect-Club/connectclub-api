<?php

namespace App\DTO\V1\Invite;

class InviteCodeResponse
{
    /** @var string */
    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }
}
