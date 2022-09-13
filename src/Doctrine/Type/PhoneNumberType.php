<?php

namespace App\Doctrine\Type;

use App\Doctrine\Listener\CacheAwareListener;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use Doctrine\DBAL\Types\Type;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberType extends Type
{
    /**
     * Phone number type name.
     */
    const NAME = 'phone_number';

    private static array $processedPhoneNumberObjects = [];

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL(array('length' => 35));
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PhoneNumber) {
            throw new ConversionException('Expected \libphonenumber\PhoneNumber, got ' . gettype($value));
        }

        $util = PhoneNumberUtil::getInstance();

        return $util->format($value, PhoneNumberFormat::E164);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?PhoneNumber
    {
        if (null === $value || $value instanceof PhoneNumber) {
            return $value;
        }

        if (isset(self::$processedPhoneNumberObjects[$value])) {
            return self::$processedPhoneNumberObjects[$value];
        }

        $cacheListeners = $platform->getEventManager()->getListeners('getCache');
        $cacheListener = array_shift($cacheListeners);

        if ($cacheListener instanceof CacheAwareListener) {
            return self::$processedPhoneNumberObjects[$value] = $cacheListener->getCache()->get(
                'phone_number_cache_'.$value,
                fn() => $this->processValue($value)
            );
        }

        return $this->processValue($value);
    }

    private function processValue(string $value): PhoneNumber
    {
        $util = PhoneNumberUtil::getInstance();
        try {
            return $util->parse($value, PhoneNumberUtil::UNKNOWN_REGION);
        } catch (NumberParseException $e) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
