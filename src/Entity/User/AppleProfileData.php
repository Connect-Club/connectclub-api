<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
class AppleProfileData
{
    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $id;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $name;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $surname;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $email;
}
