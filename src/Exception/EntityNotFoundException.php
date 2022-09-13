<?php

namespace App\Exception;

class EntityNotFoundException extends \Exception
{
    private string $entityClass;
    private int $entityId;

    public function __construct(string $entityClass, int $entityId)
    {
        $this->entityClass = $entityClass;
        $this->entityId = $entityId;

        parent::__construct('Entity '.$entityClass.' not found '.$entityId);
    }
}
