<?php

namespace App\Entity\Location;

use App\ConnectClub;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Location\CityRepository")
 */
class City
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="bigint")
     */
    public ?int $id;

    /**
     * @var Country
     * @ORM\ManyToOne(targetEntity="App\Entity\Location\Country", fetch="EAGER")
     */
    public Country $country;

    /**
     * @var string
     * @Gedmo\Translatable()
     * @ORM\Column(type="string")
     */
    public string $name;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $subdivisionFirstIsoCode;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $subdivisionFirstName;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $subdivisionSecondIsoCode;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $subdivisionSecondName;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $metroCode;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    public float $latitude;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    public float $longitude;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    public int $accuracyRadius;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    public string $timeZone;

    /** @Gedmo\Locale() */
    public string $locale = ConnectClub::DEFAULT_LANG;

    public function getId(): ?int
    {
        return $this->id;
    }
}
