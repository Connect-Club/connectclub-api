<?php

namespace App\Entity\OAuth;

use Doctrine\ORM\Mapping as ORM;
use FOS\OAuthServerBundle\Entity\Client as BaseClient;
use FOS\OAuthServerBundle\Model\ClientInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OAuth\ClientRepository")
 */
class Client extends BaseClient implements ClientInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    public $id;

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    public array $scopes = [];

    public function getId(): ?int
    {
        return $this->id;
    }
}
