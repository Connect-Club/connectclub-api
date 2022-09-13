<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MobileAppVersionRepository")
 */
class MobileAppVersion
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\Column(type="string") */
    public string $platform;

    /** @ORM\Column(type="string") */
    public string $version;

    public function getId(): ?int
    {
        return $this->id;
    }
}
