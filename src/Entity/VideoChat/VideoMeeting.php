<?php

namespace App\Entity\VideoChat;

use App\Entity\Log\LoggableEntityInterface;
use App\Entity\Log\LoggableRelatedDependencyInterface;
use App\Event\VideoRoomEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\VideoMeetingRepository")
 */
class VideoMeeting implements LoggableEntityInterface, LoggableRelatedDependencyInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     * @Groups({"default"})
     */
    public ?int $id = null;

    /**
     * @ORM\Column(type="string", unique=true)
     * @Groups({"default"})
     */
    public string $sid;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom", inversedBy="meetings")
     */
    public VideoRoom $videoRoom;

    /**
     * @var VideoMeetingParticipant[]|ArrayCollection
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\VideoChat\VideoMeetingParticipant",
     *     mappedBy="videoMeeting",
     *     cascade={"all"}
     * )
     * @ORM\OrderBy({"id": "DESC"})
     * @Groups({"default"})
     */
    public Collection $participants;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     * @Groups({"default"})
     */
    public ?int $startTime = null;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     * @Groups({"default"})
     */
    public ?int $endTime = null;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $initiator;

    /**
     * @var int|null
     * @ORM\Column(type="integer", nullable=true)
     */
    public ?int $jitsiCounter = null;

    /** @SWG\Property(type="array", items=@SWG\Schema(ref=@Model(type=VideoMeetingParticipant::class))) */
    public array $uniqueParticipants;

    /** @ORM\Column(type="integer", options={"default": 0}) */
    public int $sendNotifications = 0;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $isEmptyMeeting = false;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $videoRoomSnapshotData = [];

    public function __construct(VideoRoom $videoRoom, string $sid, ?int $startTime, ?string $initiator = null)
    {
        $this->videoRoom = $videoRoom;
        $this->sid = $sid;
        $this->startTime = $startTime;
        $this->initiator = $initiator;
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDuration(): int
    {
        if (!$this->endTime) {
            return 0;
        }

        $duration = $this->endTime - $this->startTime;

        // room deleted after 5min inactivity (twilio)
        return $this->initiator == VideoRoomEvent::INITIATOR_TWILIO ? $duration - 300 : $duration;
    }

    /**
     * @return VideoMeetingParticipant[]|ArrayCollection
     */
    public function getUniqueParticipants()
    {
        $participants = [];

        foreach ($this->participants as $participant) {
            $participants[$participant->participant->id] = $participant;
        }

        return new ArrayCollection(array_values($participants));
    }

    public function getEntityCode(): string
    {
        return 'video_meeting';
    }

    public function getDependencies(): array
    {
        return [$this->videoRoom];
    }
}
