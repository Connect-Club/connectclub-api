<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\JoinRequestActivityInterface;

class JoinRequestActivityResponse extends ActivityItemResponse
{
    public string $joinRequestId;
    public string $clubId;

    public function __construct(JoinRequestActivityInterface $activity, string $title)
    {
        parent::__construct($activity, $title);

        $this->joinRequestId = $activity->getJoinRequest()->id->toString();
        $this->clubId = $activity->getJoinRequest()->club->id->toString();
    }
}
