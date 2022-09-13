<?php

namespace App\Entity\Ethereum;

use App\Repository\Ethereum\TokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=TokenRepository::class)
 */
class Token
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $name = null;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $description = null;
    
    /** @ORM\Column(type="string") */
    public string $network;

    /** @ORM\Column(type="string") */
    public string $contractAddress;

    /** @ORM\Column(type="string") */
    public string $tokenId;

    /** @ORM\Column(type="string") */
    public string $contractType;

    /** @ORM\Column(type="integer") */
    public int $minAmount = 1;

    /** @ORM\Column(type="string", unique=true) */
    public string $landingUrl;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $initializedAt = null;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $initializedData = null;

    /** @ORM\Column(type="boolean") */
    public bool $isInternal = true;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $abi = null;
}
