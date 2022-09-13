<?php

namespace App\DTO\V1\Club;

use App\Entity\Club\Club;

class InvitationLinkResponse
{
    public string $invitationCode;

    public function __construct(Club $club)
    {
        $this->invitationCode = $club->invitationLink;
    }
}
