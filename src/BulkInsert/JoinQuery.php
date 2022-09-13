<?php

namespace App\BulkInsert;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

class JoinQuery implements QueryInterface
{
    private EntityManager $em;

    private ClassMetadata $sourceMetadata;
    private ClassMetadata $targetMetadata;
    private array $association;
    private array $rows;
    private array $columns = [];

    public function __construct(array $association, EntityManager $em)
    {
        $this->sourceMetadata = $em->getClassMetadata($association['sourceEntity']);
        $this->targetMetadata = $em->getClassMetadata($association['targetEntity']);
        $this->association = $association;

        $this->em = $em;

        foreach ($this->association['joinTable']['joinColumns'] as $joinColumn) {
            $this->columns[] = $joinColumn['name'];
        }

        foreach ($this->association['joinTable']['inverseJoinColumns'] as $joinColumn) {
            $this->columns[] = $joinColumn['name'];
        }
    }

    public function join(object $sourceEntity, object $targetEntities): void
    {
        $row = [];
        foreach ($targetEntities as $targetEntity) {
            foreach ($this->association['joinTable']['joinColumns'] as $joinColumn) {
                $row[$joinColumn['name']] = $this->getValue(
                    $this->sourceMetadata,
                    $sourceEntity,
                    $joinColumn['referencedColumnName']
                );
            }

            foreach ($this->association['joinTable']['inverseJoinColumns'] as $joinColumn) {
                $row[$joinColumn['name']] = $this->getValue(
                    $this->targetMetadata,
                    $targetEntity,
                    $joinColumn['referencedColumnName']
                );
            }
        }

        $this->rows[] = $row;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    private function getValue(ClassMetadata $metadata, object $entity, string $column): Value
    {
        $fieldMapping = $metadata->getFieldMapping(
            $metadata->getFieldName($column)
        );

        $type = Type::getType($fieldMapping['type']);

        return new Value(
            $type->convertToDatabaseValue(
                $metadata->getFieldValue($entity, $metadata->getFieldName($column)),
                $this->em->getConnection()->getDatabasePlatform()
            ),
            $type->getBindingType()
        );
    }

    public function getTableName(): string
    {
        return $this->association['joinTable']['name'];
    }
}
