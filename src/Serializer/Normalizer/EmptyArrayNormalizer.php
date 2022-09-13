<?php

namespace App\Serializer\Normalizer;

use App\Annotation\SerializationContext;
use Doctrine\Common\Annotations\Reader;
use ReflectionProperty;

class EmptyArrayNormalizer extends ObjectNormalizer
{
    private Reader $reader;
    private array $cache;

    /** @required */
    public function setReader(Reader $reader)
    {
        $this->reader = $reader;
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
        $value = parent::getAttributeValue($object, $attribute, $format, $context);

        if ($annotation = $this->getPropertySerializationContextAnnotation($object, $attribute)) {
            if ($annotation->serializeAsObject) {
                $value = is_array($value) && empty($value) ? new \stdClass() : $value;
            }
        }

        return $value;
    }

    private function getPropertySerializationContextAnnotation($object, $attribute): ?SerializationContext
    {
        try {
            $reflectionProperty = new ReflectionProperty($object, $attribute);
        } catch (\ReflectionException $exception) {
            return null;
        }

        if (!isset($this->cache[get_class($object).$attribute])) {
            $this->cache[get_class($object).$attribute] = $this->reader->getPropertyAnnotation(
                $reflectionProperty,
                SerializationContext::class
            );
        }

        return $this->cache[get_class($object).$attribute];
    }
}
