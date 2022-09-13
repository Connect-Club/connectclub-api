<?php

namespace App\DTO\V1;

class MobileAppVersionWithConfigResponse
{
    /** @var string */
    public string $version;

    /** @var MobileAppVersionConfigResponse */
    public MobileAppVersionConfigResponse $config;

    public function __construct(string $version, bool $onboarding)
    {
        $this->version = $version;
        $this->config = new MobileAppVersionConfigResponse($onboarding);
    }
}
