<?php

namespace App\DTO\V1;

use Symfony\Component\Serializer\Annotation\Groups;

class PaginatedResponse
{
    /**
     * @var array
     * @Groups({"default"})
     */
    public array $items = [];

    /**
     * @var mixed
     * @Groups({"default"})
     */
    public $lastValue;

    public function __construct(array $items, $lastValue = null)
    {
        $this->items = $items;
        $this->lastValue = $lastValue;
    }
}
