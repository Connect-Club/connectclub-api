<?php

namespace App\DTO\V1\Club;

use App\DTO\V2\User\ClubResponse;
use App\Entity\Club\Club;

class ClubMemberOfResponse extends ClubResponse
{
    /** @var string */
    public string $clubRole;

    public function __construct(Club $club, string $clubRole)
    {
        parent::__construct($club);

        $this->clubRole = $clubRole;
    }
}
