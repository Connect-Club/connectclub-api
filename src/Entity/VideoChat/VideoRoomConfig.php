<?php

namespace App\Entity\VideoChat;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\VideoRoomConfigRepository")
 */
class VideoRoomConfig
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     *
     * @Groups({"v1.room.default"})
     */
    public ?int $id;

    /**
     * @var VideoRoom
     * @ORM\OneToOne(targetEntity="App\Entity\VideoChat\VideoRoom", mappedBy="config")
     */
    public VideoRoom $videoRoom;

    /**
     * @var BackgroundPhoto|null
     * @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\BackgroundPhoto", inversedBy="videoRooms", fetch="EAGER")
     * @Groups({"v1.room.default"})
     */
    public ?BackgroundPhoto $backgroundRoom = null;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $backgroundRoomWidthMultiplier;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $backgroundRoomHeightMultiplier;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $initialRoomScale;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $minRoomZoom;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $maxRoomZoom;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $videoBubbleSize;

    /**
     * @var int
     * @ORM\Column(type="integer", options={"default": 3000})
     * @Groups({"v1.room.default"})
     */
    public int $publisherRadarSize = 3000;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"v1.room.default"})
     */
    public int $intervalToSendDataTrackInMilliseconds;

    /**
     * @var VideoRoomQuality
     * @ORM\Embedded(class="App\Entity\VideoChat\VideoRoomQuality", columnPrefix="videoQuality_")
     * @Groups({"v1.room.default"})
     */
    public VideoRoomQuality $videoQuality;

    /**
     * @ORM\Column(type="float", options={"default": 0.75})
     * @Groups({"v1.room.default"})
     */
    public float $imageMemoryMultiplier = 0.75;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     * @Groups({"v1.room.default"})
     */
    public bool $withSpeakers = false;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $dataTrackUrl = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $dataTrackApiUrl = null;

    /**
     * Room config constructor.
     */
    public function __construct(
        int $backgroundRoomWidthMultiplier,
        int $backgroundRoomHeightMultiplier,
        int $initialRoomScale,
        int $minRoomZoom,
        int $maxRoomZoom,
        int $videoBubbleSize,
        int $publisherRadarSize,
        int $intervalToSendDataTrackInMilliseconds,
        VideoRoomQuality $videoQuality
    ) {
        $this->backgroundRoomWidthMultiplier = $backgroundRoomWidthMultiplier;
        $this->backgroundRoomHeightMultiplier = $backgroundRoomHeightMultiplier;
        $this->initialRoomScale = $initialRoomScale;
        $this->minRoomZoom = $minRoomZoom;
        $this->maxRoomZoom = $maxRoomZoom;
        $this->videoBubbleSize = $videoBubbleSize;
        $this->publisherRadarSize = $publisherRadarSize;
        $this->intervalToSendDataTrackInMilliseconds = $intervalToSendDataTrackInMilliseconds;
        $this->videoQuality = $videoQuality;
    }

    /**
     * @return string
     * @Groups({"default"})
     */
    public function getDataTrackUrl()
    {
        return $_ENV['DATA_TRUCK_URL'];
    }
}
