<?php

namespace App\Entity\OAuth;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OAuth\RefreshTokenRepository")
 */
class RefreshToken extends \FOS\OAuthServerBundle\Entity\RefreshToken
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var Client
     * @ORM\ManyToOne(targetEntity="App\Entity\OAuth\Client")
     */
    protected $client;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    protected $user;

    public function getId(): ?int
    {
        return $this->id;
    }
}
