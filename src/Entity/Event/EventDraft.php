<?php

namespace App\Entity\Event;

use App\Entity\VideoChat\BackgroundPhoto;
use App\Repository\Event\EventDraftRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventDraftRepository::class)
 */
class EventDraft
{
    const TYPE_PUBLIC = 'public';
    const TYPE_PRIVATE = 'private';
    const TYPE_SMALL_BROADCASTING = 's_broadcasting';
    const TYPE_LARGE_BROADCASTING = 'l_broadcasting';
    const TYPE_SMALL_NETWORKING = 'l_networking';
    const TYPE_LARGE_NETWORKING = 's_networking';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string") */
    public string $description;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\BackgroundPhoto") */
    public BackgroundPhoto $backgroundPhoto;

    /** @ORM\Column(type="integer") */
    public int $backgroundRoomWidthMultiplier;

    /** @ORM\Column(type="integer", options={"default": 2}) */
    public int $backgroundRoomHeightMultiplier = 2;

    /** @ORM\Column(type="integer") */
    public int $index;

    /** @ORM\Column(type="boolean") */
    public bool $withSpeakers;

    /** @ORM\Column(type="integer") */
    public int $initialRoomScale;

    /** @ORM\Column(type="integer") */
    public int $publisherRadarSize;

    /** @ORM\Column(type="string") */
    public string $type = self::TYPE_SMALL_BROADCASTING;

    /** @ORM\Column(type="integer", options={"default": 5000}) */
    public int $maxParticipants = 5000;

    /** @ORM\Column(type="integer", options={"default": 5}) */
    public int $maxRoomZoom = 5;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $expectedWidth = null;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $expectedHeight = null;

    public function __construct(
        string $type,
        string $description,
        BackgroundPhoto $photo,
        int $backgroundRoomWidthMultiplier,
        int $index,
        bool $withSpeakers,
        int $initialRoomScale,
        int $publisherRadarSize
    ) {
        $this->id = Uuid::uuid4();
        $this->type = $type;
        $this->description = $description;
        $this->backgroundPhoto = $photo;
        $this->backgroundRoomWidthMultiplier = $backgroundRoomWidthMultiplier;
        $this->index = $index;
        $this->withSpeakers = $withSpeakers;
        $this->initialRoomScale = $initialRoomScale;
        $this->publisherRadarSize = $publisherRadarSize;
    }
}
