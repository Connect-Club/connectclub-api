<?php

namespace App\Entity\VideoChat;

use App\Entity\Log\LoggableEntityInterface;
use App\Entity\Log\LoggableRelatedDependencyInterface;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\VideoMeetingParticipantRepository")
 */
class VideoMeetingParticipant implements LoggableEntityInterface, LoggableRelatedDependencyInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     * @Groups({"default"})
     */
    public ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoMeeting", inversedBy="participants")
     */
    public VideoMeeting $videoMeeting;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @Groups({"default"})
     */
    public User $participant;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"default"})
     */
    public ?string $sid;

    /**
     * @ORM\Column(type="bigint")
     * @Groups({"default"})
     */
    public int $startTime;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $jitsiEndpointUuid = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $jitsiConferenceId = null;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     * @Groups({"default"})
     */
    public ?int $endTime = null;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $endpointAllowIncomingMedia = false;

    public function __construct(
        VideoMeeting $videoMeeting,
        User $participant,
        int $startTime,
        ?string $jitsiEndpointUuid = null,
        bool $endpointAllowIncomingMedia = false
    ) {
        $this->videoMeeting = $videoMeeting;
        $this->participant = $participant;
        $this->startTime = $startTime;
        $this->jitsiEndpointUuid = $jitsiEndpointUuid;
        $this->endpointAllowIncomingMedia = $endpointAllowIncomingMedia;
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

        return $this->endTime - $this->startTime;
    }

    public function getEntityCode(): string
    {
        return 'video_meeting_participant';
    }

    public function getDependencies(): array
    {
        return [$this->videoMeeting, $this->videoMeeting->videoRoom];
    }
}
