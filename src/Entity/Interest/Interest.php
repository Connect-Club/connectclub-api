<?php

namespace App\Entity\Interest;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Interest\InterestRepository")
 */
class Interest
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Interest\InterestGroup", inversedBy="interests") */
    public ?InterestGroup $group = null;

    /** @ORM\Column(type="string") */
    public string $name;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $languageCode = null;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $automaticChooseForRegionCodes = null;

    /** @ORM\Column(type="boolean", nullable=true) */
    public ?bool $isDefaultInterestForRegions = false;

    /** @ORM\Column(type="integer", options={"default": 0}) */
    public int $row = 0;

    /** @ORM\Column(type="integer", options={"default": 0}) */
    public int $globalSort = 0;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    public bool $isOld = true;

    public function __construct(
        InterestGroup $group,
        string $name,
        int $row = 0,
        bool $isOld = true,
        int $globalSort = 0
    ) {
        $this->group = $group;
        $this->name = $name;
        $this->row = $row;
        $this->isOld = $isOld;
        $this->globalSort = $globalSort;
    }
}
