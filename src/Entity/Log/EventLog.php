<?php

namespace App\Entity\Log;

use App\Repository\Log\EventLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EventLogRepository::class)
 */
class EventLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\Column(type="string") */
    public string $entityCode;

    /** @ORM\Column(type="string") */
    public string $entityId;

    /** @ORM\Column(type="string") */
    public string $eventCode;

    /** @ORM\Column(type="json") */
    public array $context;

    /** @ORM\OneToMany(targetEntity="App\Entity\Log\EventLogRelation", mappedBy="eventLog", cascade="all") */
    public Collection $relations;

    /** @ORM\Column(type="bigint") */
    public int $time;

    public function __construct(
        string $entityCode,
        ?string $entityId,
        string $eventCode,
        array $context = []
    ) {
        $this->entityCode = mb_substr($entityCode, 0, 255);
        $this->entityId = mb_substr($entityId, 0, 255) ?? '';
        $this->eventCode = mb_substr($eventCode, 0, 255);
        $this->context = $context;
        $this->relations = new ArrayCollection();
        $this->time = (int) round(microtime(true) * 1000);
    }

    public function addRelation(string $entityCode, ?string $entityId)
    {
        $this->relations->add(new EventLogRelation($this, $entityCode, $entityId ?? ''));
    }
}
