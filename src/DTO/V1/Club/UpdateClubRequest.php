<?php

namespace App\DTO\V1\Club;

use App\DTO\V1\Interests\InterestDTO;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateClubRequest
{
    /**
     * @Assert\NotBlank(allowNull=true, message="Title value should not be blank")
     * @Assert\Length(max=250, maxMessage="Title should have {{ limit }} characters or less.")
     */
    public ?string $title = null;

    public ?string $description = null;

    public ?int $imageId = null;

    /**
     * @var InterestDTO[]|null
     */
    public ?array $interests = null;

    public ?bool $isPublic = null;
}
