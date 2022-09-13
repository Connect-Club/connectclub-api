<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;

interface FilterInterface
{
    public function support(string $entityClass, array $filtersRequest): bool;
    public function handle(QueryBuilder $queryBuilder, string $entityClass, string $mainAlias, array &$filters);
}
