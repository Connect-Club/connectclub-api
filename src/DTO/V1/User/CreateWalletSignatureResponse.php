<?php

namespace App\DTO\V1\User;

class CreateWalletSignatureResponse
{
    /** @var string */
    public string $nonce;

    /** @var string */
    public string $message;

    public function __construct(string $nonce, string $message)
    {
        $this->nonce = $nonce;
        $this->message = $message;
    }
}
