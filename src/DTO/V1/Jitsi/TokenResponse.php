<?php

namespace App\DTO\V1\Jitsi;

class TokenResponse
{
    /** @var string */
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}
