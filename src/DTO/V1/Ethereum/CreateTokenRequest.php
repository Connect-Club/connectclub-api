<?php

namespace App\DTO\V1\Ethereum;

class CreateTokenRequest
{
    /** @var string */
    public $addressId;
    /** @var string */
    public $tokenId;
    /** @var string */
    public $contractType;
    /** @var int */
    public $minAmount;
    /** @var string */
    public $landingUrl;
    /** @var bool */
    public $isInternal;
}
