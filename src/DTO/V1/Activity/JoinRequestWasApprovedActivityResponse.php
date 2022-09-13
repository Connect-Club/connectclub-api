<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\JoinRequestWasApprovedActivity;

class JoinRequestWasApprovedActivityResponse extends ActivityItemResponse
{
    public string $clubId;

    public function __construct(JoinRequestWasApprovedActivity $activity, string $title)
    {
        parent::__construct($activity, $title);

        $this->clubId = $activity->club->id->toString();
    }
}
