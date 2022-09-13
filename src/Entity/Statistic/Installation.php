<?php

namespace App\Entity\Statistic;

use App\Repository\Statistic\InstallationRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=InstallationRepository::class)
 */
class Installation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string", unique=true) */
    public string $deviceId;

    /** @ORM\Column(type="string") */
    public string $ip;

    /** @ORM\Column(type="string", length=4, nullable=true) */
    public ?string $countryIsoCode;

    /** @ORM\Column(type="string") */
    public string $platform;

    /** @ORM\Column(type="boolean", options={"default": 1}) */
    public bool $isFirstInstall = true;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $utm = null;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(string $deviceId, string $ip, ?string $countryIsoCode = null)
    {
        $this->id = Uuid::uuid4();
        $this->deviceId = $deviceId;
        $this->ip = $ip;
        $this->countryIsoCode = $countryIsoCode;
        $this->createdAt = (int) round(microtime(true) * 1000);
    }
}
