<?php

namespace App\Message;

use Symfony\Component\Lock\Key;

class SendSmsMessage
{
    public string $phoneNumber;
    public ?Key $jwtClaimLockKey;
    public ?string $claim;
    public ?string $ip;

    public function __construct(
        string $phoneNumber,
        ?string $ip = null,
        ?string $claim = null,
        ?Key $jwtClaimLockKey = null
    ) {
        $this->phoneNumber = $phoneNumber;
        $this->ip = $ip;
        $this->claim = $claim;
        $this->jwtClaimLockKey = $jwtClaimLockKey;
    }
}
