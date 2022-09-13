<?php

namespace App\Serializer\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class StdClassObjectNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = [])
    {
        return $object;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \stdClass;
    }
}
