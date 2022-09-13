<?php

namespace App\DTO\V1;

class InstallationStatisticRequest
{
    /** @var string */
    public string $deviceId;

    /** @var string */
    public string $platform;

    /** @var string|null */
    public ?string $utm = null;
}
