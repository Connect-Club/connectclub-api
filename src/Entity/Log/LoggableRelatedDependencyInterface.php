<?php

namespace App\Entity\Log;

interface LoggableRelatedDependencyInterface
{
    /** @return LoggableEntityInterface[] */
    public function getDependencies(): array;
}
