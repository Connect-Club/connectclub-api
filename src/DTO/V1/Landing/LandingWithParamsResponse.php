<?php

namespace App\DTO\V1\Landing;

use App\Annotation\SerializationContext;
use App\Entity\Landing\Landing;
use Swagger\Annotations as SWG;

class LandingWithParamsResponse extends LandingInfoResponse
{
    /**
     * @SerializationContext(serializeAsObject=true)
     * @SWG\Property(type="array", @SWG\Items(type="string"))
     */
    public $params;

    public function __construct(Landing $landing)
    {
        parent::__construct($landing);

        $this->params = $landing->params;
    }
}
