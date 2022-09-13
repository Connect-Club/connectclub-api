<?php

namespace App\DTO\V1\Ethereum;

class UserTokenResponse
{
    public string $tokenId;
    public string $title;
    public ?string $description;
    public string $preview;

    public function __construct(string $tokenId, string $title, ?string $description, string $preview)
    {
        $this->tokenId = $tokenId;
        $this->title = $title;
        $this->description = $description;
        $this->preview = $preview;
    }
}
