<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\CustomActivity;

class ActivityCustomResponse extends ActivityItemResponse
{
    /** @var string */
    public string $body;

    /** @var string|null */
    public ?string $externalLink;

    public function __construct(CustomActivity $activity)
    {
        parent::__construct($activity, $activity->title);

        $this->body = $activity->text;
        $this->externalLink = $activity->getExternalLink();
    }
}
