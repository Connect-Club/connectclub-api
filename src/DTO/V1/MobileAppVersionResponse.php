<?php

namespace App\DTO\V1;

class MobileAppVersionResponse
{
    /** @var string */
    public string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }
}
