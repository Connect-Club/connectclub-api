<?php

namespace App\DTO\V1\Subscription;

use App\Entity\Subscription\Subscription;

class CreateResponse
{
    public string $id;

    public function __construct(Subscription $subscription)
    {
        $this->id = $subscription->id->toString();
    }
}
