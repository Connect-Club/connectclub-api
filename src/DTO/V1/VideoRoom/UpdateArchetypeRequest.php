<?php

namespace App\DTO\V1\VideoRoom;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateArchetypeRequest extends CreateArchetypeRequest
{
    /**
     * @Assert\NotBlank(message="v1.error.archetype.code.required", allowNull=false)
     * @var string
     */
    public $code;
}
