<?php

namespace App\PropertyInfo;

class Type extends \Symfony\Component\PropertyInfo\Type
{
    public ?string $id;

    public function __construct(
        string $builtinType,
        bool $nullable = false,
        string $class = null,
        string $id = null,
        bool $collection = false,
        Type $collectionKeyType = null,
        Type $collectionValueType = null
    ) {
        $this->id = $id;

        parent::__construct($builtinType, $nullable, $class, $collection, $collectionKeyType, $collectionValueType);
    }
}
