<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
class GoogleProfileData
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

    /**
     * @var string|null
     * @ORM\Column(type="text", nullable=true)
     */
    public ?string $picture;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $locale;
}
