<?php

namespace App\Repository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMapping;

trait LastValuePaginationQueryTrait
{
    /**
     * @return EntityManager
     */
    abstract protected function getEntityManager();

    private function createPaginationQuery(
        string $nativeFetchingSQL,
        ResultSetMapping $rsm,
        int $lastValue,
        int $limit,
        string $over = 'ORDER BY q.cnt DESC'
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
