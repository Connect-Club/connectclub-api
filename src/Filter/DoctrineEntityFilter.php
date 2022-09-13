<?php

namespace App\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

abstract class DoctrineEntityFilter extends BaseFilter
{
    /** @var array */
    private array $metaDataEntityCache = [];
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    protected function getFieldAndOperation(string $filterField): array
    {
        if (preg_match('/(\=|\<\=|\>\=|\!\=|<|>|\!)/', $filterField, $matches)) {
            $operator = $matches[1];
            $filterField = str_replace($operator, '', $filterField);
        } else {
            $operator = '=';
        }

        return [$filterField, $operator];
    }

    protected function handleEntityFilter(
        QueryBuilder $queryBuilder,
        string $entityClass,
        string $alias,
        string $filterField,
        $filterValue
    ) {
        list ($filterField, $operator) = $this->getFieldAndOperation($filterField);

        $field = null;
        if (is_array($filterValue)) {
            foreach ($filterValue as $cn => $cnVal) {
                if (is_string($cn)) {
                    $metadata = $this->metaDataEntityCache[$entityClass] ?? null;
                    if ($metadata && isset($metadata->embeddedClasses[$filterField])) {
                        $field = sprintf('%s.%s.%s', $alias, $filterField, $cn);
                        $filterValue = $cnVal;
                    }
                }
            }
        }
        if (!$field) {
            $field = sprintf('%s.%s', $alias, $filterField);
        }

        $queryBuilder->andWhere(
            $this->createExpression($field, $filterValue, $operator)
        );
    }

    /**
     * @param string $targetEntity
     * @param string $fieldName
     * @return array|null
     */
    protected function getAssociationMapping(string $targetEntity, string $fieldName)
    {
        $associationMappings = $this->getClassMetaData($targetEntity)->getAssociationMappings();

        foreach ($associationMappings as $associationMapping) {
            if ($associationMapping['fieldName'] === $fieldName) {
                return $associationMapping;
            }
        }

        return null;
    }

    /**
     * @param string $targetEntity
     * @return ClassMetadata
     */
    protected function getClassMetaData(string $targetEntity)
    {
        if (!isset($this->metaDataEntityCache[$targetEntity])) {
            $this->metaDataEntityCache[$targetEntity] = $this->entityManager->getClassMetadata($targetEntity);
        }

        return $this->metaDataEntityCache[$targetEntity];
    }

    private function createExpression($columnWithoutType, $filterValue, $operator = null)
    {
        $operator = $operator ?? '=';

        $expr = null;
        $exprBuilder = new Expr();
        if ($filterValue === 'null') {
            $filterValue = null;
        }

        switch ($operator) {
            case '=':
                if (is_null($filterValue)) {
                    $expr = $exprBuilder->isNull($columnWithoutType);
                } else {
                    if (is_array($filterValue)) {
                        $expr = $exprBuilder->in($columnWithoutType, $filterValue);
                    } else {
                        $expr = $exprBuilder->eq(
                            $columnWithoutType,
                            is_string($filterValue) ? $exprBuilder->literal($filterValue) : $filterValue
                        );
                    }
                }
                break;
            case '!=':
                if (is_array($filterValue)) {
                    $expr = $exprBuilder->notIn($columnWithoutType, $filterValue);
                } else {
                    $expr = $exprBuilder->neq($columnWithoutType, $filterValue);
                }
                break;
            case '<':
                if (!is_array($filterValue)) {
                    $expr = $exprBuilder->lt($columnWithoutType, $filterValue);
                } else {
                    foreach ($filterValue as $val) {
                        $expr = $this->createExpression($columnWithoutType, $val, $operator);
                    }
                }
                break;
            case '>':
                if (!is_array($filterValue)) {
                    $expr = $exprBuilder->gt($columnWithoutType, $filterValue);
                } else {
                    foreach ($filterValue as $val) {
                        $expr = $this->createExpression($columnWithoutType, $val, $operator);
                    }
                }
                break;
            case '>=':
                if (is_array($filterValue)) {
                    $expr = $exprBuilder->notIn($columnWithoutType, $filterValue);
                } else {
                    $expr = $exprBuilder->gte($columnWithoutType, $filterValue);
                }
                break;
            case '<=':
                if (is_array($filterValue)) {
                    $expr = $exprBuilder->notIn($columnWithoutType, $filterValue);
                } else {
                    $expr = $exprBuilder->lte($columnWithoutType, $filterValue);
                }
                break;
        }

        return $expr;
    }
}
