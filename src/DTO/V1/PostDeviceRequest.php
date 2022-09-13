<?php

namespace App\DTO\V1;

use Swagger\Annotations as SWG;

class PostDeviceRequest
{
    /** @var string */
    public ?string $deviceId = null;
    /** @var string */
    public ?string $locale = null;
    /** @var string */
    public ?string $pushToken = null;
    /** @var string */
    public ?string $model = null;
    /**
     * @SWG\Property(type="string")
     * @var string|integer
     */
    public $timeZone = null;
    /** @var string|null */
    public ?string $type = null;
}
