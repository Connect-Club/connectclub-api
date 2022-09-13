<?php

namespace App\DTO\V1\User;

use Symfony\Component\Validator\Constraints as Assert;

class PatchUserProfile
{
    /** @var string[]|null */
    public $badges;

    /** @var string|null */
    public $name;

    /** @var string|null */
    public $about;

    /** @var string|null */
    public $surname;

    /** @var int|null */
    public $countInvites;

    /** @var string|null */
    public $shortBio;

    /** @var string|null */
    public $longBio;

    /**
     * @var string|null
     * @Assert\Regex(pattern="/^[a-zA-Z_.\-\d]+$/", message="incorrect_value")
     * @Assert\NotBlank(allowNull=true, message="cannot_be_empty")
     */
    public $username;

    public ?bool $isSuperCreator = null;
}
