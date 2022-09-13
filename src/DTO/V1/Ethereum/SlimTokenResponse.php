<?php

namespace App\DTO\V1\Ethereum;

use App\DTO\V1\Club\ClubResponse;
use App\DTO\V1\Club\ClubSlimResponse;
use App\Entity\Ethereum\Token;

class SlimTokenResponse
{
    /** @var string */
    public string $id;

    /** @var string */
    public string $ethereumTokenId;

    /** @var string */
    public string $contractAddress;

    /** @var string */
    public string $name;

    /** @var string */
    public string $description;

    public function __construct(Token $token)
    {
        $this->id = $token->id->toString();
        $this->ethereumTokenId = $token->tokenId;
        $this->contractAddress = $token->contractAddress;
        $this->name = $token->name ?? '';
        $this->description = $token->description ?? '';
    }
}
