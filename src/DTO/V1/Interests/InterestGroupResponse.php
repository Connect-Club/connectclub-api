<?php

namespace App\DTO\V1\Interests;

use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;

class InterestGroupResponse
{
    /** @var int */
    public int $id;
    /** @var string */
    public string $name;
    /** @var InterestDTO[] */
    public array $interests = [];

    public function __construct(InterestGroup $interestGroup)
    {
        $this->id = (int) $interestGroup->id;
        $this->name = $interestGroup->name;
        $this->interests = array_map(fn(Interest $i) => new InterestDTO($i), $interestGroup->interests->toArray());
    }
}
