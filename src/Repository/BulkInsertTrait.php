<?php

namespace App\Repository;

use App\BulkInsert\Query;
use App\BulkInsert\QueryExecutor;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

trait BulkInsertTrait
{
    /**
     * @return EntityManager
     * @noinspection PhpMissingReturnTypeInspection
     */
    abstract protected function getEntityManager();

    /**
     * @return ClassMetadata
     * @noinspection PhpMissingReturnTypeInspection
     */
    abstract protected function getClassMetadata();

    public function bulkInsert(): Query
    {
        return new Query(
            $this->getClassMetadata(),
            $this->getEntityManager()
        );
    }

    public function executeBulkInsert(Query $bulkInsert, bool $onConflictDoNothing = false): void
    {
        $executor = new QueryExecutor($this->getEntityManager(), $onConflictDoNothing);
        $executor->execute($bulkInsert);
    }
}
