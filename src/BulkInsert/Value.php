<?php

namespace App\BulkInsert;

class Value
{
    public int $type;

    /** @var mixed */
    public $value;

    public function __construct($value, int $type)
    {
        $this->value = $value;
        $this->type = $type;
    }
}
