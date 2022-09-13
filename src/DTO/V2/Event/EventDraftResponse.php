<?php

namespace App\DTO\V2\Event;

use App\Entity\Event\EventDraft;

class EventDraftResponse
{
    public string $id;
    public string $type;
    public int $backgroundId;
    public string $backgroundSrc;
    public ?int $requiredBackgroundWidth = null;
    public ?int $requiredBackgroundHeight = null;

    public function __construct(EventDraft $eventDraft)
    {
        $this->id = $eventDraft->id->toString();
        $this->type = $eventDraft->type;
        $this->backgroundId = $eventDraft->backgroundPhoto->id;
        $this->backgroundSrc = $eventDraft->backgroundPhoto->getOriginalUrl();
        $this->requiredBackgroundWidth = $eventDraft->expectedWidth;
        $this->requiredBackgroundHeight = $eventDraft->expectedHeight;
    }
}
