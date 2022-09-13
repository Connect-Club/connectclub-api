<?php

namespace App\DTO\V1\User;

use App\Entity\OAuth\AccessToken;

class LastAccessTokenResponse
{
    /** @var int|null */
    public ?int $id;
    /** @var string */
    public string $accessToken;
    /** @var int */
    public int $expiresAt;
    /** @var int */
    public int $expiresIn;
    /** @var bool */
    public bool $hasExpired;

    public function __construct(AccessToken $token)
    {
        $this->id = $token->getId();
        $this->accessToken = $token->getToken();
        $this->expiresAt = $token->getExpiresAt();
        $this->hasExpired = $token->hasExpired();
        $this->expiresIn = $token->hasExpired() ? 0 : $token->getExpiresIn();
    }
}
