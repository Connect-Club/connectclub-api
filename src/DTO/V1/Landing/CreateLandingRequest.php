<?php

namespace App\DTO\V1\Landing;

use Swagger\Annotations as SWG;

class CreateLandingRequest
{
    /** @var string|null */
    public $name = null;
    /** @var string|null */
    public $status = null;
    /** @var string|null */
    public $url = null;
    /** @var string|null */
    public $title = null;
    /** @var string|null */
    public $subtitle = null;
    /**
     * @SWG\Property(type="object")
     * @var array|null
     */
    public $params = null;
}
