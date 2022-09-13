<?php

namespace App\DTO\V1\Interests;

use App\Entity\Interest\Interest;

class InterestDTO
{
    /** @var int */
    public int $id;

    /** @var string */
    public string $name;

    public function __construct(Interest $interest)
    {
        $this->id = (int) $interest->id;
        $this->name = $interest->name;
    }
}
