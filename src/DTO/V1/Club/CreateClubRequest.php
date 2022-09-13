<?php

namespace App\DTO\V1\Club;

use App\DTO\V1\Interests\InterestDTO;
use Symfony\Component\Validator\Constraints as Assert;

class CreateClubRequest
{
    /**
     * @var string
     * @Assert\NotBlank(allowNull=false, message="Title value should not be blank")
     * @Assert\Length(max=250, maxMessage="Title should have {{ limit }} characters or less.")
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var integer
     */
    public $imageId;

    /**
     * @var InterestDTO[]|null
     */
    public $interests = null;

    /** @var int|null */
    public $ownerId = null;

    /** @var bool|null */
    public $isPublic = true;
}
