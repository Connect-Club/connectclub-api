<?php

namespace App\Entity\VideoRoom;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\VideoRoom\VideoRoomParticipantStatisticRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=VideoRoomParticipantStatisticRepository::class)
 */
class VideoRoomParticipantStatistic
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    private UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    private VideoRoom $videoRoom;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    private User $participant;

    /** @ORM\Column(type="string") */
    private string $endpointUuid;

    /** @ORM\Column(type="string") */
    private string $conferenceId;

    /** @ORM\Column(type="float") */
    private float $rtt;

    /** @ORM\Column(type="float") */
    private float $jitter;

    /** @ORM\Column(type="bigint") */
    private int $commutativePacketsLost;

    /** @ORM\Column(type="bigint") */
    private int $createdAt;

    public function __construct(
        VideoRoom $videoRoom,
        User $participant,
        string $endpointUuid,
        string $conferenceId,
        float $rtt,
        float $jitter,
        int $commutativePacketsLost,
        int $createdAt
    ) {
        $this->id = Uuid::uuid4();
        $this->videoRoom = $videoRoom;
        $this->participant = $participant;
        $this->endpointUuid = $endpointUuid;
        $this->conferenceId = $conferenceId;
        $this->rtt = $rtt;
        $this->jitter = $jitter;
        $this->commutativePacketsLost = $commutativePacketsLost;
        $this->createdAt = $createdAt;
    }
}
