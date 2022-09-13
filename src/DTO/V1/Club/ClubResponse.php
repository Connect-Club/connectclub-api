<?php

namespace App\DTO\V1\Club;

use App\DTO\V2\Interests\InterestDTO;
use App\Entity\Club\Club;
use App\Entity\Interest\Interest;

class ClubResponse extends ClubSlimResponse
{
    public ?string $description = null;
    public ?string $avatar = null;
    public ?int $countParticipants = null;
    /** @var InterestDTO[] */
    public array $interests = [];
    public ClubUser $owner;

    public function __construct(Club $club, ?int $countParticipants = null)
    {
        parent::__construct($club);

        $this->avatar = $club->avatar ? $club->avatar->getResizerUrl() : null;
        $this->description = $club->description;
        $this->countParticipants = $countParticipants;
        $this->interests = array_map(fn(Interest $i) => new InterestDTO($i), $club->interests->toArray());
        $this->owner = new ClubUser($club->owner);
    }
}
