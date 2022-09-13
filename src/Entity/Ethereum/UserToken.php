<?php

namespace App\Entity\Ethereum;

use App\Entity\Photo\NftImage;
use App\Entity\User;
use App\Repository\Ethereum\UserTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UserTokenRepository::class)
 */
class UserToken
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="string")
     */
    public string $tokenId;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $user;

    /** @ORM\Column(type="string") */
    public string $name;

    /** @ORM\OneToOne(targetEntity="App\Entity\Photo\NftImage", cascade={"persist"}) */
    public NftImage $nftImage;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $description;
}
