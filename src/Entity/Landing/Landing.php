<?php

namespace App\Entity\Landing;

use App\Entity\User;
use App\Repository\Landing\LandingRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=LandingRepository::class)
 */
class Landing
{
    const STATUS_ACTIVE = 'active';
    const STATUS_HIDE = 'hide';
    const STATUS_DELETE = 'delete';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /**
     * @ORM\Column(type="string")
     */
    public string $name;

    /**
     * @ORM\Column(type="string")
     */
    public string $status;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    public string $url;

    /**
     * @ORM\Column(type="string")
     */
    public string $title;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $subtitle = null;

    /**
     * @ORM\Column(type="json")
     */
    public array $params = [];

    /**
     * @ORM\Column(type="bigint")
     */
    public int $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public User $createdBy;

    public function __construct(
        User $createdBy,
        string $name,
        string $status,
        string $url,
        string $title,
        array $params,
        ?string $subtitle = null
    ) {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->status = $status;
        $this->url = $url;
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->params = $params;
        $this->createdBy = $createdBy;
        $this->createdAt = time();
    }
}
