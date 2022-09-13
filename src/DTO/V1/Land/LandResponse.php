<?php

namespace App\DTO\V1\Land;

use App\Entity\Land\Land;

class LandResponse
{
    public string $id;
    public int $number;
    public string $name;
    public ?string $description = null;
    public ?string $thumb = null;
    public ?string $image = null;
    public int $sector;
    public float $x;
    public float $y;
    public ?string $ownerAddress = null;
    public ?string $ownerUsername = null;
    public bool $available;
    public ?string $roomId = null;
    public ?string $roomPassword = null;
    public ?string $roomDescription = null;

    public function __construct(Land $land)
    {
        $this->id = $land->id->toString();
        $this->number = $land->number ?? 0;
        $this->name = $land->name;
        $this->description = $land->description;
        $this->thumb = $land->thumb ? $land->thumb->getResizerUrl() : null;
        $this->image = $land->image ? $land->image->getResizerUrl() : null;
        $this->sector = $land->sector;
        $this->x = $land->x;
        $this->y = $land->y;
        $this->ownerAddress = $land->owner ? $land->owner->wallet : null;
        $this->ownerUsername = $land->owner ? $land->owner->username : null;
        $this->available = $land->available;
        if ($land->room) {
            $this->roomId = $land->room->community->name;
            $this->roomPassword = $land->room->community->password;
            $this->roomDescription = $land->room->community->description;
        }
    }
}
