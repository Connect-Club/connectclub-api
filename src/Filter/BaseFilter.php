<?php
/**
 * Created by PhpStorm.
 * User: anboo
 * Date: 08.01.19
 * Time: 11:21
 */

namespace App\Filter;

use Doctrine\ORM\Query\AST\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class BaseFilter
 */
abstract class BaseFilter implements FilterInterface
{
    protected QueryBuilder $queryBuilder;

    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    protected function getJoinAlias(QueryBuilder $queryBuilder, $mainAlias, $fieldRelation)
    {
        $joins = $queryBuilder->getDQLPart('join') ?? [];
        $joins = isset($joins[$mainAlias]) ? $joins[$mainAlias] : [];

        foreach ($joins as $join) {
            if ($join->getJoin() == $mainAlias.'.'.$fieldRelation) {
                return $join->getAlias();
            }
        }

        return null;
    }

    abstract public function support(string $entityClass, array $filtersRequest): bool;
    abstract public function handle(
        QueryBuilder $queryBuilder,
        string $entityClass,
        string $mainAlias,
        array &$filters
    );
}
