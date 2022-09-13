<?php

namespace App\Entity\Location;

use App\ConnectClub;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Location\CountryRepository")
 */
class Country
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     */
    public ?int $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $continentCode;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $continentName;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $isoCode;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    public bool $isInEuropeanUnion;

    /**
     * @var string
     * @Gedmo\Translatable()
     * @ORM\Column(type="string")
     */
    public string $name;

    /** @Gedmo\Locale() */
    public string $locale = ConnectClub::DEFAULT_LANG;

    public function getId(): ?int
    {
        return $this->id;
    }
}
