<?php

namespace App\DTO\V1\Land;

use Symfony\Component\Validator\Constraints as Assert;

class CreateLandRequest
{
    /**
     * @var string|null
     * @Assert\NotBlank(message="must_set")
     * @Assert\Length(max=255, maxMessage="max_characters_limit", min=1, minMessage="min_characters_limit")
     */
    public $name = null;

    /**
     * @var int|null
     * @Assert\Type(type="integer", message="not_valid")
     * @Assert\NotBlank(message="must_set", allowNull=true)
     */
    public $ownerId = null;

    /**
     * @var string|null
     * @Assert\Type(type="string", message="not_valid")
     */
    public $roomId = null;

    /** @var string|null */
    public $description = null;

    /**
     * @var float|null
     * @Assert\NotBlank(message="must_set", allowNull=false)
     * @Assert\Type(type="float", message="not_valid")
     * @Assert\GreaterThan(-1, message="not_valid")
     */
    public $x = null;

    /**
     * @var float|null
     * @Assert\NotBlank(message="must_set", allowNull=false)
     * @Assert\Type(type="float", message="not_valid")
     * @Assert\GreaterThan(-1, message="not_valid")
     */
    public $y = null;

    /**
     * @var int|null
     * @Assert\GreaterThan(-1, message="not_valid")
     * @Assert\NotBlank(message="must_set")
     */
    public $sector = null;

    /**
     * @var int|null
     * @Assert\Type(type="integer", message="not_valid")
     */
    public $thumbId = null;

    /**
     * @var int|null
     * @Assert\Type(type="integer", message="not_valid")
     */
    public $imageId = null;

    /**
     * @var bool|null
     * @Assert\Type(type="boolean", message="not_valid")
     */
    public $available = null;
}
