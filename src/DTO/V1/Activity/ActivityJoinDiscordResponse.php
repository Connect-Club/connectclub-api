<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\JoinDiscordActivity;

class ActivityJoinDiscordResponse extends ActivityItemResponse
{
    public ?string $link;

    public function __construct(JoinDiscordActivity $activity, string $title)
    {
        parent::__construct($activity, $title);

        $this->link = $activity->getLink();
    }
}
