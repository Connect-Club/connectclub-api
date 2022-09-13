<?php

namespace App\BulkInsert;

use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class QueryExecutor
{
    private EntityManagerInterface $em;
    private bool $onConflictDoNothing;

    public function __construct(EntityManagerInterface $em, bool $onConflictDoNothing = false)
    {
        $this->em = $em;
        $this->onConflictDoNothing = $onConflictDoNothing;
    }

    public function execute(QueryInterface $query): void
    {
        $connection = $this->em->getConnection();

        $connection->beginTransaction();

        try {
            $this->doExecute($query);

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    private function doExecute(QueryInterface $query): void
    {
        if (!$query->getRows()) {
            return;
        }

        [$params, $paramTypes, $placeholders] = $this->getParams($query);

        $columnsSql = implode(', ', array_map(fn($column) => "\"$column\"", $query->getColumns()));
        $valuesSql = $this->formatPlaceholders($placeholders);

        $onConflictQuery = $this->onConflictDoNothing ? 'ON CONFLICT DO NOTHING' : '';

        $sql = <<<SQL
            INSERT INTO "{$query->getTableName()}" ($columnsSql)
            VALUES ($valuesSql) $onConflictQuery
        SQL;

        $this->em->getConnection()->executeQuery($sql, $params, $paramTypes);

        if ($query instanceof Query) {
            foreach ($query->getJoinQueries() as $joinQuery) {
                $this->doExecute($joinQuery);
            }
        }
    }

    private function getParams(QueryInterface $query): array
    {
        $values = [];
        $valueTypes = [];
        $placeholders = [];
        foreach ($query->getRows() as $row) {
            $rowPlaceholders = [];
            foreach ($query->getColumns() as $column) {
                if (isset($row[$column])) {
                    /** @var Value $value */
                    $value = $row[$column];

                    $values[] = $value->value;
                    $valueTypes[] = $value->type;
                    $rowPlaceholders[] = '?';
                } else {
                    $rowPlaceholders[] = 'default';
                }
            }
            $placeholders[] = $rowPlaceholders;
        }

        return [
            $values,
            $valueTypes,
            $placeholders
        ];
    }

    private function formatPlaceholders(array $placeholders): string
    {
        return implode("),\n(", array_map(
            fn($rowPlaceholders) => implode(', ', $rowPlaceholders),
            $placeholders
        ));
    }
}
