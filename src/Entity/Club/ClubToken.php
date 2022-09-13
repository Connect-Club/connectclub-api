<?php

namespace App\Entity\Club;

use App\Entity\Ethereum\Token;
use App\Repository\Club\ClubTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=ClubTokenRepository::class)
 */
class ClubToken
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Club\Club")
     */
    public Club $club;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ethereum\Token")
     */
    public Token $token;
}
