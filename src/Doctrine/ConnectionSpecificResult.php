<?php

namespace App\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class ConnectionSpecificResult
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private array $result;

    public function __construct(EntityManagerInterface $entityManager, array $result)
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        $this->result = $result;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getResult(): array
    {
        return $this->result;
    }
}
