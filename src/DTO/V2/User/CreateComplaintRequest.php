<?php

namespace App\DTO\V2\User;

use Symfony\Component\Validator\Constraints as Assert;

class CreateComplaintRequest
{
    /**
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     * @var string
     */
    public $reason;

    /**
     * @var string|null
     */
    public $description = null;
}
