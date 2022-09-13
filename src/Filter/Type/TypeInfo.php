<?php

namespace App\Filter\Type;

use Symfony\Component\PropertyInfo\Type;

/**
 * Class TypeInfo
 */
class TypeInfo
{
    private string $class;
    private string $property;
    private Type $type;

    public function __construct(string $class, string $property, Type $type)
    {
        $this->class = $class;
        $this->property = $property;
        $this->type = $type;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getType(): Type
    {
        return $this->type;
    }
}
