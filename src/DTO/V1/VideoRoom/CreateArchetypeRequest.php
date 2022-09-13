<?php

namespace App\DTO\V1\VideoRoom;

use App\Annotation\SerializationContext;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Annotation\Groups;

class CreateArchetypeRequest
{

    /**
     * @SWG\Property(type="object")
     * @Groups({"default"})
     * @SerializationContext(serializeAsObject=true)
     */
    public array $custom = [];
}
