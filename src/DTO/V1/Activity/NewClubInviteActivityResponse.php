<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\NewClubInviteActivity;

class NewClubInviteActivityResponse extends ActivityItemResponse
{
    public string $clubId;

    public function __construct(NewClubInviteActivity $activity, string $title)
    {
        $this->clubId = $activity->getClub()->id->toString();

        parent::__construct($activity, $title);
    }
}
