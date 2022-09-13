<?php

namespace App\Entity;

use App\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SettingsRepository::class)
 */
class Settings
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="integer")
     */
    public int $id = 1;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $showFestivalBanner = false;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $dataTrackUrl = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $dataTrackApiUrl = null;
}
