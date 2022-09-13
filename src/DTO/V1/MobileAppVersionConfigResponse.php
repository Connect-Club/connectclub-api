<?php

namespace App\DTO\V1;

class MobileAppVersionConfigResponse
{
    /** @var bool */
    public bool $onboarding;

    public function __construct(bool $onboarding)
    {
        $this->onboarding = $onboarding;
    }
}
