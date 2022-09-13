<?php

namespace App\DTO\V1\Subscription;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateRequest
{
    /**
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     * @Assert\Length(max=255)
     */
    public string $name;
    /**
     * @Assert\Length(max=1000)
     * @Assert\NotNull(message="cannot_be_null")
     */
    public string $description;
    /**
     * @Assert\NotNull(message="cannot_be_empty")
     */
    public bool $isActive;
}
