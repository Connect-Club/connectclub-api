<?php

namespace App\DTO\V2\User;

use App\Entity\Club\Club;

class ClubResponse
{
    public string $id;
    public string $title;
    public ?string $avatar;

    public function __construct(Club $club)
    {
        $this->id = $club->id->toString();
        $this->title = $club->title;
        $this->avatar = $club->avatar ? $club->avatar->getResizerUrl() : null;
    }
}
