<?php

namespace App\Swagger\ModelDescriber;

use App\Controller\ErrorCode;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\Common\Annotations\Reader;
use EXSyst\Component\Swagger\Response;
use EXSyst\Component\Swagger\Schema;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class CustomModelDescriber implements ModelDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;

    private Reader $doctrineReader;
    private ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(Reader $doctrineReader, ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->doctrineReader = $doctrineReader;
        $this->classMetadataFactory = $classMetadataFactory;
    }

    public function describe(Model $model, Schema $schema)
    {
        /** @var ListResponse|ViewResponse $annotation */
        $annotation = $model->getOptions()['annotation'];
        /** @var Response $oldResponseSchema */
        $oldResponseSchema = $model->getOptions()['responseSchema'];
        $groups = $model->getOptions()['groups'];

        /** @var string $exampleErrorSchema */
        $exampleErrorSchema = [];
        $errorResponseSchema = new Schema();
        $errorResponseSchema->setType('array');
        $errorResponseSchema->getItems()->setType('string');
        $errorResponseSchema->setExample($exampleErrorSchema);

        $schema->getProperties()->set('requestId', (new Schema())->setType('string')->setTitle('Unique request uuid'));
        $schema->getProperties()->set('errors', $errorResponseSchema);
        $schema->setDescription($oldResponseSchema->getDescription());

        $serializationGroups = $groups ?? $annotation->groups ?? [];

        if ($annotation->entityClass && !$serializationGroups) {
            $metadata = $this->classMetadataFactory->getMetadataFor($annotation->entityClass);
            foreach ($metadata->getAttributesMetadata() as $attributeMetadata) {
                if (in_array('default', $attributeMetadata->getGroups())) {
                    $serializationGroups[] = 'default';
                    $serializationGroups = array_unique($serializationGroups);
                    break;
                }
            }
        }

        if ($annotation->entityClass) {
            $type = new Type(Type::BUILTIN_TYPE_OBJECT, false, $annotation->entityClass);
            $responseSchema = new Schema();
            switch (get_class($annotation)) {
                case ListResponse::class:
                    $ref = $this->modelRegistry->register(new Model($type, $serializationGroups));

                    if ($annotation->paginationByLastValue) {
                        $responseSchemaItems = new Schema();
                        $responseSchemaItems->setType(Type::BUILTIN_TYPE_ARRAY);
                        $responseSchemaItems->getItems()->setRef($ref);
                        $responseSchema->getProperties()->set('items', $responseSchemaItems);
                        $responseSchema->getProperties()->set('lastValue', new Schema(['type' => 'integer']));

                        if ($annotation->paginationWithTotalCount) {
                            $responseSchema->getProperties()->set('totalCount', new Schema(['type' => 'integer']));
                        }
                    } else {
                        $responseSchema->setType(Type::BUILTIN_TYPE_ARRAY);
                        $responseSchema->getItems()->setRef($ref);
                    }

                    break;

                case ViewResponse::class:
                    $responseSchema->setType('object');
                    $ref = $this->modelRegistry->register(new Model($type, $serializationGroups));
                    $responseSchema->setRef($ref);
                    break;

                default:
                    return;
            }
            $schema->getProperties()->set('response', $responseSchema);
        } else {
            $schema->getProperties()->set('response', new Schema(['type' => Type::BUILTIN_TYPE_OBJECT]));
        }
    }

    public function supports(Model $model): bool
    {
        $options = $model->getOptions();

        return $options && isset($options['annotation']);
    }
}
