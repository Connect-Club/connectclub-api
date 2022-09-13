<?php

namespace App\Serializer\Normalizer;

use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PhoneNumberNormalizer implements NormalizerInterface
{
    /**
     * @param PhoneNumber $object
     * @param string|null $format
     * @param array $context
     * @return string
     */
    public function normalize($object, string $format = null, array $context = []): string
    {
        return PhoneNumberUtil::getInstance()->format($object, PhoneNumberFormat::E164);
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof PhoneNumber;
    }
}
