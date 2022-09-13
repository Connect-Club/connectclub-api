<?php

namespace App\DTO\V1\User;

class CreateWalletRequest
{
    /** @var string */
    public $address;

    /** @var string */
    public $signature;

    /** @var string */
    public $text;
}
