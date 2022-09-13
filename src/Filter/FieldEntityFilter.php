<?php

namespace App\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class FieldEntityFilter extends DoctrineEntityFilter
{
    /** @var string[] */
    private array $filterFields = [];
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct($entityManager);
    }

    public function support(string $entityClass, array $filtersRequest): bool
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);

        foreach ($filtersRequest as $filterField => $filterValue) {
            list ($filterFieldEntity, ) = $this->getFieldAndOperation($filterField);

            if (isset($metadata->fieldNames[$filterFieldEntity])) {
                $this->filterFields[$filterField] = $filterValue;
                unset($filtersRequest[$filterField]); //Reserve filter key for this filter
            }
        }

        return !empty($this->filterFields);
    }

    public function handle(QueryBuilder $queryBuilder, string $entityClass, string $mainAlias, array &$filters)
    {
        foreach ($this->filterFields as $filterField => $filterValue) {
            $this->handleEntityFilter($queryBuilder, $entityClass, $mainAlias, $filterField, $filterValue);
        }
    }
}
