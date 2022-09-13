<?php

namespace App\DTO\V2\User;

use App\DTO\V1\Club\ClubUser;
use App\Entity\Club\ClubParticipant;
use App\Entity\Invite\Invite;

class InvitedToResponse extends ClubResponse
{
    public string $title;
    public int $joinedAt;
    public ?ClubUser $by = null;

    public function __construct(Invite $invite)
    {
        parent::__construct($invite->club);

        $this->title = $invite->club->title;
        $this->joinedAt = $invite->createdAt;
        $this->by = new ClubUser($invite->author);
    }
}
