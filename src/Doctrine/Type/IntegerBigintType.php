<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BigIntType;

class IntegerBigintType extends BigIntType
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        $value = parent::convertToPHPValue($value, $platform);

        return null === $value ? null : (int) $value;
    }
}
