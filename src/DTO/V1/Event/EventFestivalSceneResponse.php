<?php

namespace App\DTO\V1\Event;

use App\Entity\EventScheduleFestivalScene;

class EventFestivalSceneResponse
{
    /** @var string */
    public string $id;

    /** @var string */
    public string $sceneCode;

    public function __construct(EventScheduleFestivalScene $eventFestivalScene)
    {
        $this->id = $eventFestivalScene->id->toString();
        $this->sceneCode = $eventFestivalScene->sceneCode;
    }
}
