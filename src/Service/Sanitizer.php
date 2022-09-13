<?php

namespace App\Service;

use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class Sanitizer
{
    private PropertyInfoExtractorInterface $propertyInfoExtractor;

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
    }

    public function sanitize(QueryBuilder $queryBuilder, string $field, $value)
    {
        $type = $this->getPropertyTypeForQuery($queryBuilder, $field);

        switch ($type) {
            case Type::BUILTIN_TYPE_INT:
                return intval($value);

            default:
                return $value;
        }
    }

    private function getPropertyTypeForQuery(QueryBuilder $queryBuilder, string $field): string
    {
        $from = $queryBuilder->getDQLPart('from');

        $aliasesAssociation = array_combine(
            array_map(fn (From $rootAlias) => $rootAlias->getAlias(), $from),
            array_map(fn (From $rootAlias) => $rootAlias->getFrom(), $from)
        );

        list($alias, $field) = explode('.', $field);

        if (isset($aliasesAssociation[$alias])) {
            $type = $this->propertyInfoExtractor->getTypes($aliasesAssociation[$alias], $field);
            return $type[0]->getBuiltinType();
        }

        $joinAliasesGroups = $queryBuilder->getDQLPart('join');

        foreach ($joinAliasesGroups as $rootAlias => $joinAliasesGroup) {
            $rootEntityClass = $aliasesAssociation[$rootAlias];

            foreach ($joinAliasesGroup as $joinAlias) {
                if (is_array($joinAlias)) {
                    $joinField = str_replace($rootAlias.'.', '', $joinAlias['join']);
                } else {
                    /** @var Join $joinAlias */
                    $joinField = str_replace($rootAlias.'.', '', $joinAlias->getJoin());
                    $joinAlias = $joinAlias->getAlias();
                }

                $type = $this->propertyInfoExtractor->getTypes($rootEntityClass, $joinField);
                $type = $type[0];
                if ($type->isCollection()) {
                    $joinEntity = $type->getCollectionValueType()->getClassName();
                } else {
                    $joinEntity = $type->getClassName();
                }

                $aliasesAssociation[$joinAlias] = $joinEntity;
            }
        }

        $targetEntity = $aliasesAssociation[$alias];

        $type = $this->propertyInfoExtractor->getTypes($targetEntity, $field)[0];

        return $type->getBuiltinType();
    }
}
