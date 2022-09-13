<?php

namespace App\Entity\User;

use App\Repository\User\LanguageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LanguageRepository::class)
 */
class Language
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\Column(type="string", length=4) */
    public string $code;

    /** @ORM\Column(type="string", length=50) */
    public string $name;

    /** @ORM\Column(type="boolean", nullable=true) */
    public ?bool $isDefaultInterestForRegions;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $automaticChooseForRegionCodes = null;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $sort;

    public function __construct(
        string $name,
        string $code,
        ?bool $isDefaultInterestForRegions = null,
        ?int $sort = null,
        ?array $automaticChooseForRegionCodes = null
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->isDefaultInterestForRegions = $isDefaultInterestForRegions;
        $this->sort = $sort;
        $this->automaticChooseForRegionCodes = $automaticChooseForRegionCodes;
    }
}
