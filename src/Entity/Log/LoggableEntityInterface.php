<?php

namespace App\Entity\Log;

interface LoggableEntityInterface
{
    public function getEntityCode(): string;
}
