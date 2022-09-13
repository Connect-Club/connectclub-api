<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MobileAppConfigRepository")
 */
class MobileAppConfig
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id;

    /** @ORM\Column(type="string") */
    public string $platform;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $onboarding = false;
}
