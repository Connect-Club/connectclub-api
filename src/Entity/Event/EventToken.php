<?php

namespace App\Entity\Event;

use App\Entity\Ethereum\Token;
use App\Repository\Event\EventTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventTokenRepository::class)
 */
class EventToken
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule", inversedBy="forOwnerTokens") */
    public EventSchedule $eventSchedule;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Ethereum\Token") */
    public Token $token;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(EventSchedule $eventSchedule, Token $token)
    {
        $this->id = Uuid::uuid4();
        $this->eventSchedule = $eventSchedule;
        $this->token = $token;
        $this->createdAt = time();
    }
}
