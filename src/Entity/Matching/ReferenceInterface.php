<?php

namespace App\Entity\Matching;

use Ramsey\Uuid\UuidInterface;

interface ReferenceInterface
{
    public function getId(): UuidInterface;
    public function getName(): string;
}
