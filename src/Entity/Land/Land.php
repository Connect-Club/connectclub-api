<?php

namespace App\Entity\Land;

use App\Entity\Photo\Image;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Land\LandRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=LandRepository::class)
 */
class Land
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string") */
    public string $name;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public ?User $owner = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    public ?VideoRoom $room = null;

    /** @ORM\Column(type="text") */
    public ?string $description = null;

    /**
     * @ORM\Column(type="integer")
     * @ORM\SequenceGenerator(sequenceName="land_number_seq", initialValue=1)
     */
    public int $number;

    /** @ORM\Column(type="float") */
    public float $x;

    /** @ORM\Column(type="float") */
    public float $y;

    /** @ORM\Column(type="integer") */
    public int $sector;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Photo\Image") */
    public ?Image $thumb = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Photo\Image") */
    public ?Image $image = null;

    /** @ORM\Column(type="boolean") */
    public bool $available = true;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $createdBy;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(string $name, float $x, float $y, int $sector, User $createdBy)
    {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->x = $x;
        $this->y = $y;
        $this->sector = $sector;
        $this->createdBy = $createdBy;
        $this->createdAt = time();
    }
}
