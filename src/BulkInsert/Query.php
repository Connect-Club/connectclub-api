<?php

namespace App\BulkInsert;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use RuntimeException;

class Query implements QueryInterface
{
    private EntityManager $em;

    private ClassMetadata $metadata;
    private array $rows = [];
    private array $columns = [];

    /** @var JoinQuery[] */
    private array $joinQueries = [];

    public function __construct(ClassMetadata $metadata, EntityManager $em)
    {
        if ($metadata->idGenerator->isPostInsertGenerator()) {
            throw new RuntimeException('Entity must have predefined id');
        }

        if (!$this->supportsInheritanceType($metadata)) {
            throw new RuntimeException(__CLASS__.' only supports Single table or Table per class inheritance types');
        }

        $this->em = $em;
        $this->metadata = $metadata;

        if ($metadata->isRootEntity() && $this->metadata->discriminatorMap) {
            foreach ($this->metadata->discriminatorMap as $className) {
                foreach ($em->getClassMetadata($className)->getColumnNames() as $column) {
                    $this->columns[$column] = $column;
                }
            }
        } else {
            foreach ($metadata->getColumnNames() as $column) {
                $this->columns[$column] = $column;
            }
        }

        if ($this->metadata->discriminatorColumn) {
            $this->columns[] = $this->metadata->discriminatorColumn['name'];
        }

        $this->addAssociationColumns();

        $this->createJoinQueries();

        $this->columns = array_values($this->columns);
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return JoinQuery[]
     */
    public function getJoinQueries(): array
    {
        return $this->joinQueries;
    }

    public function getTableName(): string
    {
        return $this->metadata->getTableName();
    }

    public function insertEntity(object $entity): void
    {
        $metadata = $this->getEntityMetadata($entity);

        $row = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            $row[$metadata->getColumnName($fieldName)] = $this->getValue($metadata, $entity, $fieldName);
        }

        if ($metadata->discriminatorColumn) {
            $row[$metadata->discriminatorColumn['name']] = $this->getDiscriminatorColumnValue($metadata);
        }

        foreach ($metadata->getAssociationMappings() as $association) {
            $relatedMetaData = $this->em->getClassMetadata($association['targetEntity']);

            if (!$association['isOwningSide']) {
                continue;
            }

            if (isset($association['joinTable'])) {
                $joinQuery = $this->joinQueries[$association['joinTable']['name']];
                $joinQuery->join(
                    $entity,
                    $metadata->getFieldValue($entity, $association['fieldName'])
                );
            } else {
                foreach ($association['joinColumns'] as $joinColumn) {
                    $row[$joinColumn['name']] = $this->getRelationColumnValue(
                        $relatedMetaData,
                        $metadata->getFieldValue($entity, $association['fieldName']),
                        $joinColumn['referencedColumnName']
                    );
                }
            }
        }

        $this->rows[] = $row;
    }

    private function getValue(ClassMetadata $metadata, object $entity, string $field): Value
    {
        $fieldMapping = $metadata->getFieldMapping($field);

        $type = Type::getType($fieldMapping['type']);

        return new Value(
            $type->convertToDatabaseValue(
                $metadata->getFieldValue($entity, $field),
                $this->em->getConnection()->getDatabasePlatform()
            ),
            $type->getBindingType()
        );
    }

    private function getDiscriminatorColumnValue(ClassMetadata $metadata): Value
    {
        $type = Type::getType($metadata->discriminatorColumn['type']);

        return new Value(
            $type->convertToDatabaseValue(
                $metadata->discriminatorValue,
                $this->em->getConnection()->getDatabasePlatform()
            ),
            $type->getBindingType()
        );
    }

    private function getRelationColumnValue(ClassMetadata $fieldMetadata, object $relatedEntity, string $column): Value
    {
        $field = $fieldMetadata->getFieldName($column);
        $fieldMapping = $fieldMetadata->getFieldMapping($field);

        $type = Type::getType($fieldMapping['type']);

        return new Value(
            $type->convertToDatabaseValue(
                $fieldMetadata->getFieldValue($relatedEntity, $field),
                $this->em->getConnection()->getDatabasePlatform()
            ),
            $type->getBindingType()
        );
    }

    private function addAssociationColumns(): void
    {
        if ($this->metadata->isRootEntity() && $this->metadata->discriminatorMap) {
            foreach ($this->metadata->discriminatorMap as $className) {
                foreach ($this->em->getClassMetadata($className)->getAssociationMappings() as $association) {
                    if (!$association['isOwningSide'] || !isset($association['joinColumns'])) {
                        continue;
                    }

                    foreach ($association['joinColumns'] as $column) {
                        $this->columns[$column['name']] = $column['name'];
                    }
                }
            }
        } else {
            foreach ($this->metadata->getAssociationMappings() as $association) {
                if (!$association['isOwningSide'] || !isset($association['joinColumns'])) {
                    continue;
                }

                foreach ($association['joinColumns'] as $column) {
                    $this->columns[$column['name']] = $column['name'];
                }
            }
        }
    }

    private function createJoinQueries(): void
    {
        foreach ($this->metadata->getAssociationMappings() as $association) {
            if (!$association['isOwningSide'] || !isset($association['joinTable'])) {
                continue;
            }

            $this->joinQueries[$association['joinTable']['name']] = new JoinQuery(
                $association,
                $this->em
            );
        }
    }

    private function getEntityMetadata(object $entity): ClassMetadata
    {
        return $this->em->getClassMetadata(get_class($entity));
    }

    private function supportsInheritanceType(ClassMetadata $metadata): bool
    {
        return $metadata->isInheritanceTypeNone()
            || $metadata->isInheritanceTypeSingleTable()
            || $metadata->isInheritanceTypeTablePerClass();
    }
}
