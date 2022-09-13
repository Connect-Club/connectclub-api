<?php

namespace App\DTO\V1;

class PaginatedResponseWithCount extends PaginatedResponse
{
    /** @var int */
    public int $totalCount = 0;

    public function __construct(array $items, $lastValue = null, int $totalCount = 0)
    {
        $this->totalCount = $totalCount;

        parent::__construct($items, $lastValue);
    }
}
