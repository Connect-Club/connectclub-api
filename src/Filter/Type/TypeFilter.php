<?php

namespace App\Filter\Type;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

class TypeFilter implements TypeFilterInterface
{
    public function supportType(TypeInfo $typeInfo, $searchValue): bool
    {
        return $typeInfo->getType()->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT;
    }

    public function handleType(QueryBuilder $builder, TypeInfo $typeInfo, string $field, $searchValue)
    {
    }
}
