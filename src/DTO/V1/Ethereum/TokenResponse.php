<?php

namespace App\DTO\V1\Ethereum;

use App\DTO\V1\Club\ClubResponse;
use App\DTO\V1\Club\ClubSlimResponse;

class TokenResponse
{
    public string $url;

    public string $contractAddress;

    public int $totalSupply;

    public int $maxTokenSupply;

    public string $tokenPrice;

    public string $infuraUrl;

    public ?int $balanceOf;

    /** @var ClubResponse[] */
    public array $clubs;

    public string $network;

    public function __construct(
        string $url,
        string $contractAddress,
        int $totalSupply,
        int $maxTokenSupply,
        string $tokenPrice,
        $infuraUrl,
        ?int $balanceOf,
        array $clubs,
        string $network
    ) {
        $this->url = $url;
        $this->contractAddress = $contractAddress;
        $this->totalSupply = $totalSupply;
        $this->maxTokenSupply = $maxTokenSupply;
        $this->tokenPrice = $tokenPrice;
        $this->infuraUrl = $infuraUrl;
        $this->balanceOf = $balanceOf;
        $this->clubs = $clubs ?? [];
        $this->network = $network;
    }
}
