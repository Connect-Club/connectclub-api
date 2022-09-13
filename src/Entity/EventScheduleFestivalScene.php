<?php

namespace App\Entity;

use App\Repository\EventScheduleFestivalSceneRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventScheduleFestivalSceneRepository::class)
 */
class EventScheduleFestivalScene
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string") */
    public ?string $festivalCode = null;

    /** @ORM\Column(type="string") */
    public string $sceneCode;

    public function __construct(string $sceneCode, ?string $festivalCode = null)
    {
        $this->id = Uuid::uuid4();
        $this->sceneCode = $sceneCode;
        $this->festivalCode = $festivalCode;
    }
}
