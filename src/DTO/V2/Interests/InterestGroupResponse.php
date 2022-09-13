<?php

namespace App\DTO\V2\Interests;

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
        foreach ($interestGroup->getInterests() as $interest) {
            $this->interests[$interest->row] ??= [];
            $this->interests[$interest->row][] = new InterestDTO($interest);
        }
        $this->interests = array_values($this->interests);
    }
}
