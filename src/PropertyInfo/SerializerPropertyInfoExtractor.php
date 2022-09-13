<?php

namespace App\PropertyInfo;

use App\Swagger\RouteDescriber\CustomRouteDescriber;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class SerializerPropertyInfoExtractor implements PropertyListExtractorInterface
{
    private ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    public function getProperties($class, array $context = [])
    {
        if (!isset($context['serializer_groups'])) {
            return null;
        }

        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $properties = [];
        $prefix = CustomRouteDescriber::PREFIX_INTERNAL_GROUPS;
        $groups = array_filter($context['serializer_groups'], fn (string $group) => false === strpos($group, $prefix));

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            $propertyGroups = $serializerAttributeMetadata->getGroups();
            if (!$groups || array_intersect($groups, $propertyGroups)) {
                $properties[] = $serializerAttributeMetadata->getName();
            }
        }

        return $properties;
    }
}
