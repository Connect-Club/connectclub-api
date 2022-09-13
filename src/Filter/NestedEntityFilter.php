<?php


namespace App\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use App\Filter\Type\TypeFilterInterface;
use App\Filter\Type\TypeInfo;

/**
 * Class NestedEntityFilter
 */
class NestedEntityFilter extends DoctrineEntityFilter
{
    const HANDLED_FILTER_VALUE = '__FILTERED__';

    /** @var EntityManagerInterface */
    private EntityManagerInterface $entityManager;

    /** @var array */
    private array $usedAliases = [];

    /** @var TypeFilterInterface[] */
    private iterable $typeFilters;

    /** @var PropertyInfoExtractorInterface */
    private PropertyInfoExtractorInterface $propertyInfo;

    /** @var array */
    private array $mapFilters = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        PropertyInfoExtractorInterface $propertyInfo,
        iterable $typeFilters
    ) {
        $this->entityManager = $entityManager;
        $this->propertyInfo = $propertyInfo;
        $this->typeFilters = $typeFilters;

        parent::__construct($entityManager);
    }

    public function support(string $entityClass, array $filtersRequest): bool
    {
        $this->createTypeFilterMap($entityClass, 'e', '', $filtersRequest);

        foreach ($filtersRequest as $filterKey => $filterValue) {
            if ($this->getAssociationMapping($entityClass, $filterKey) && $filterValue) {
                return true;
            }
        }

        return !empty($this->mapFilters);
    }

    private function createTypeFilterMap(
        string $entityClass,
        string $mainAlias,
        string $aliasToNestedClass,
        array $filtersRequest,
        array $keys = []
    ) {
        $metadata = $this->getClassMetaData($entityClass);

        foreach ($filtersRequest as $filterKey => $filterValue) {
            $lastMainAlias = $mainAlias;

            $associationMapping = $this->getAssociationMapping($entityClass, $filterKey);
            if (isset($metadata->embeddedClasses[$filterKey])) {
                $nestedClass = $metadata->embeddedClasses[$filterKey]['class'];
                $aliasToNestedClass = $aliasToNestedClass ? $aliasToNestedClass.'.'.$filterKey : $filterKey;
            } elseif (!is_null($filterValue) && $associationMapping) {
                $nestedClass = $associationMapping['targetEntity'];
                $mainAlias = $this->addJoin($this->queryBuilder, $mainAlias, $filterKey, $entityClass, $nestedClass);
                $aliasToNestedClass = '';
            } else {
                continue;
            }

            $foundSupportedTypeFilter = false;
            $types = $this->propertyInfo->getTypes($entityClass, $filterKey);
            if ($types) {
                $typeInfo = new TypeInfo($nestedClass, $filterKey, $types[0]);
                foreach ($this->typeFilters as $typeFilter) {
                    if ($typeFilter->supportType($typeInfo, $filterValue)) {
                        $foundSupportedTypeFilter = true;
                        $this->mapFilters[] = [
                            'keys' => $keys,
                            'alias' => $mainAlias,
                            'field' => $aliasToNestedClass,
                            'typeInfo' => $typeInfo,
                            'value' => $filterValue,
                        ];
                        $aliasToNestedClass = '';
                    }
                }
            }

            if (is_array($filterValue) && !$foundSupportedTypeFilter) {
                $keys[] = $filterKey;
                $this->createTypeFilterMap($nestedClass, $mainAlias, $aliasToNestedClass, $filterValue, $keys);
            }

            $mainAlias = $lastMainAlias;
            $keys = [];
        }
    }

    public function handle(QueryBuilder $queryBuilder, string $entityClass, string $mainAlias, array &$filters)
    {
        $pa = PropertyAccess::createPropertyAccessor();

        foreach ($this->mapFilters as $mapFilter) {
            foreach ($this->typeFilters as $typeFilter) {
                if ($typeFilter->supportType($mapFilter['typeInfo'], $mapFilter['value'])) {
                    $typeFilter->handleType(
                        $queryBuilder,
                        $mapFilter['typeInfo'],
                        $mapFilter['alias'].'.'.$mapFilter['field'],
                        $mapFilter['value']
                    );

                    $keys = $mapFilter['keys'];
                    for ($i = 0; $i < count($keys); $i++) {
                        $propertyPath = '['.implode('][', $keys).']';
                        $data = $pa->getValue($filters, $propertyPath);
                        if (is_array($data)) {
                            if (count($data) > 1) {
                                $propertyPath = $propertyPath.'['.$mapFilter['field'].']';
                            }

                            $pa->setValue($filters, $propertyPath, self::HANDLED_FILTER_VALUE);
                        }
                        array_pop($keys);
                    }

                    if (isset($filters[$mapFilter['field']]) && is_scalar($filters[$mapFilter['field']])) {
                        unset($filters[$mapFilter['field']]);
                    }
                }
            }
        }

        $this->removeEmptyFilters($filters);

        foreach ($filters as $filterField => $filterValue) {
            if ($filterValue) {
                $this->handleRow($queryBuilder, $entityClass, $mainAlias, [$filterField => $filterValue]);
                unset($filters[$filterField]);
            }
        }
    }

    private function removeEmptyFilters(&$haystack)
    {
        foreach ($haystack as $key => $value) {
            if (is_array($value)) {
                $haystack[$key] = $this->removeEmptyFilters($haystack[$key]);
            }

            if ($haystack[$key] === self::HANDLED_FILTER_VALUE) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    private function handleRow(QueryBuilder $queryBuilder, string $entityClass, string $alias, array $row)
    {
        foreach ($row as $filterField => $filterValue) {
            if ($associationMapping = $this->getAssociationMapping($entityClass, $filterField)) {
                $newAlias = $this->addJoin(
                    $queryBuilder,
                    $alias,
                    $filterField,
                    $entityClass,
                    $associationMapping['targetEntity']
                );
                $this->handleRow($queryBuilder, $associationMapping['targetEntity'], $newAlias, $filterValue);
            } else {
                $this->handleEntityFilter($queryBuilder, $entityClass, $alias, $filterField, $filterValue);
            }
        }
    }

    private function addJoin(
        QueryBuilder $queryBuilder,
        string $mainAlias,
        string $filterField,
        string $fromEntity,
        string $toEntity
    ) : string {
        if (!$joinAlias = $this->getJoinAlias($queryBuilder, $mainAlias, $filterField)) {
            $joinAlias = $this->createAliasFor($filterField, $fromEntity, $toEntity);
            $queryBuilder->join(sprintf('%s.%s', $mainAlias, $filterField), $joinAlias);
        } else {
            $this->useAlias($fromEntity, $toEntity, $joinAlias);
        }

        return $joinAlias;
    }

    /**
     * @param string $filterField
     * @param string $fromEntity
     * @param string $toEntity
     * @return string
     */
    private function createAliasFor(string $filterField, string $fromEntity, string $toEntity)
    {
        $variant = '_'.$filterField;

        $counter = 0;
        while ($this->getAlias($fromEntity, $toEntity) === $variant) {
            $variant = $variant.$counter;
            $counter += 1;
        }

        $this->useAlias($fromEntity, $toEntity, $variant);

        return $variant;
    }

    /**
     * @param string $fromEntity
     * @param string $toEntity
     * @param string $alias
     */
    private function useAlias(string $fromEntity, string $toEntity, string $alias)
    {
        $this->usedAliases[$fromEntity.$toEntity] = $alias;
    }

    /**
     * @param string $fromEntity
     * @param string $toEntity
     * @return string|null
     */
    private function getAlias(string $fromEntity, string $toEntity): ?string
    {
        return isset($this->usedAliases[$fromEntity.$toEntity]) ? $this->usedAliases[$fromEntity.$toEntity] : null;
    }
}
