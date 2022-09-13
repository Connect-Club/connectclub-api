<?php

namespace App\DTO\V1\Reference;

use App\Entity\Matching\ReferenceInterface;

class ReferenceResponse
{
    /** @var string */
    public string $id;

    /** @var string */
    public string $name;

    public function __construct(ReferenceInterface $reference)
    {
        $this->id = $reference->getId()->toString();
        $this->name = $reference->getName();
    }
}
