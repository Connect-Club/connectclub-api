<?php

namespace App\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer as BaseObjectNormalizer;

class ObjectNormalizer extends BaseObjectNormalizer
{
    protected function instantiateObject(
        array &$data,
        $class,
        array &$context,
        \ReflectionClass $reflectionClass,
        $allowedAttributes,
        string $format = null
    ) {
        try {
            return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
        } catch (MissingConstructorArgumentsException $missingConstructorArgumentsException) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }
    }
}
