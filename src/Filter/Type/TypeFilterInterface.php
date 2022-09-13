<?php

namespace App\Filter\Type;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface TypeFilterInterface
 */
interface TypeFilterInterface
{
    public function supportType(TypeInfo $typeInfo, $searchValue): bool;
    public function handleType(QueryBuilder $builder, TypeInfo $typeInfo, string $field, $searchValue);
}
