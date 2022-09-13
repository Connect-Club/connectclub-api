<?php

namespace App\Repository;

use App\Doctrine\CursorPaginateWalker;
use App\Entity\Activity\Activity;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Tools\Pagination\CountOutputWalker;

trait HandleNativeQueryLastValuePaginationTrait
{
    public function getResult(NativeQuery $nativeQuery, NativeQuery $nativeCountQuery): array
    {
        $count = $nativeCountQuery->getSingleScalarResult();
        $lastValue = 0;
        $result = [];

        foreach ($nativeQuery->getResult() as $item) {
            $lastValue = $item['row'];
            unset($item['row']);

            $entity = count($item) == 1 ? array_values($item)[0] : $item;

            $result[] = $entity;
        }

        if (!$result || $count == $lastValue) {
            $lastValue = null;
        }

        return [$result, $lastValue, $count];
    }

    public function getSimpleResult(
        string $entityClass,
        Query $query,
        ?int $lastValue,
        int $limit = 20,
        $orderByField = null,
        $order = null
    ) {
        $query
            ->setHint(CursorPaginateWalker::HINT_ENTITY_CLASS, $entityClass)
            ->setHint(CursorPaginateWalker::HINT_LAST_VALUE, $lastValue)
            ->setHint(CursorPaginateWalker::HINT_LIMIT, $limit);

        if ($orderByField && $order) {
            $query
                ->setHint(CursorPaginateWalker::HINT_ORDER_BY_FIELD, $orderByField)
                ->setHint(CursorPaginateWalker::HINT_ORDER_BY, $order);
        }

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('dctrn_count', 'count');

        $countQuery = $this->cloneQuery($query);
        $count = $countQuery->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            CountOutputWalker::class
        )->setResultSetMapping($rsm)->getSingleScalarResult();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, CursorPaginateWalker::class);
        $query->setHint(CursorPaginateWalker::MAX_COUNT_HINT, $count);
        $query->setHint(Query::HINT_INCLUDE_META_COLUMNS, true);

        return $query->getResult('CursorPaginateHydrator');
    }

    private function cloneQuery(Query $query): Query
    {
        $cloneQuery = clone $query;

        $cloneQuery->setParameters(clone $query->getParameters());
        $cloneQuery->setCacheable(false);

        foreach ($query->getHints() as $name => $value) {
            $cloneQuery->setHint($name, $value);
        }

        return $cloneQuery;
    }

    private function createPaginationQuery(
        string $nativeFetchingSQL,
        ResultSetMapping $rsm,
        int $lastValue,
        int $limit,
        string $over = ''
    ): NativeQuery {
        $em = $this->getEntityManager();

        $rsm->addScalarResult('row', 'row', Types::INTEGER);

        return $em->createNativeQuery("
            SELECT *
            FROM (
                SELECT
                    *,
                    ROW_NUMBER() OVER ($over) as row
                FROM (
                    $nativeFetchingSQL
                ) q
            ) q2
            WHERE q2.row > :lastValue
            LIMIT :limit
        ", $rsm)
            ->setParameter('limit', $limit)
            ->setParameter('lastValue', $lastValue, Types::INTEGER);
    }

    private function createCountQuery(
        string $nativeFetchingSQL,
        ResultSetMapping $rsm,
        NativeQuery $paginationQuery
    ): NativeQuery {
        $em = $this->getEntityManager();

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'e', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery("SELECT COUNT(e) as cnt FROM ($nativeFetchingSQL) e", $rsmCount);
        $nativeQueryCount->setParameters($paginationQuery->getParameters());

        return $nativeQueryCount;
    }
}
