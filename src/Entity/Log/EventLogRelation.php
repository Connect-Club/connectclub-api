<?php

namespace App\Entity\Log;

use App\Repository\Log\EventLogRelationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EventLogRelationRepository::class)
 */
class EventLogRelation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Log\EventLog", inversedBy="relations") */
    public EventLog $eventLog;

    /** @ORM\Column(type="string") */
    public string $entityCode;

    /** @ORM\Column(type="string") */
    public string $entityId;

    /** @ORM\Column(type="bigint") */
    public int $time;

    public function __construct(EventLog $eventLog, string $entityCode, string $entityId)
    {
        $this->eventLog = $eventLog;
        $this->entityCode = $entityCode;
        $this->entityId = $entityId;
        $this->time = (int) round(microtime(true) * 1000);
    }
}
