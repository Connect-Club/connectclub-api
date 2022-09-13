<?php

namespace App\DTO\V1\Subscription;

use Symfony\Component\Validator\Constraints as Assert;

class CreateRequest
{
    /**
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     * @Assert\Length(max=255)
     */
    public string $name;
    /**
     * @Assert\Length(max=1000)
     * @Assert\NotNull
     */
    public string $description;
    /**
     * @Assert\NotNull(message="cannot_be_empty")
     */
    public bool $isActive;
    /**
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     * @Assert\Choice(
     *     callback={"App\Entity\Subscription\Subscription", "getPriceChoices"},
     *     message="is_not_a_valid_choice"
     * )
     */
    public int $price;
}
