<?php

namespace App\Entity\User;

use App\Entity\User;
use App\Repository\User\WalletRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass=WalletRepository::class)
 */
class Wallet
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    private Uuid $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    private User $owner;

    /**
     * @ORM\Column(type="text", unique=true)
     */
    private string $address;

    /**
     * @ORM\Column(type="text", unique=true)
     */
    private string $signature;

    public function __construct(User $owner, string $address, string $signature)
    {
        $this->owner = $owner;
        $this->address = $address;
        $this->signature = $signature;
    }
}
