<?php

namespace App\Transaction;

use App\Service\Transaction\CommittableTransaction;
use App\Service\Transaction\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Class FlushEntityManagerTransaction.
 */
class FlushEntityManagerTransaction implements Transaction, CommittableTransaction
{
    private EntityManagerInterface $currentEntityManager;
    private array $entities;

    public function __construct(EntityManagerInterface $entityManager, ...$entities)
    {
        $this->currentEntityManager = $entityManager;
        $this->entities = $entities;
    }

    public function up()
    {
        try {
            $this->currentEntityManager->getConnection()->beginTransaction();

            foreach ($this->entities as $entity) {
                $this->currentEntityManager->persist($entity);
                $this->currentEntityManager->flush();
            }
        } catch (Throwable $e) {
            if ($this->currentEntityManager->getConnection()->isTransactionActive()) {
                $this->currentEntityManager->rollback();
            }

            throw $e;
        }
    }

    public function down()
    {
        $this->currentEntityManager->getConnection()->rollback();
    }

    public function commit()
    {
        $this->currentEntityManager->commit();
    }
}
