<?php

namespace App\Swagger\RouteDescriber;

use App\PropertyInfo\Type;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\Common\Annotations\Reader;
use EXSyst\Component\Swagger\Parameter;
use EXSyst\Component\Swagger\Response;
use EXSyst\Component\Swagger\Schema;
use EXSyst\Component\Swagger\Swagger;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

class CustomRouteDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    const PREFIX_INTERNAL_GROUPS = '__route_';

    use ModelRegistryAwareTrait;
    use RouteDescriberTrait;

    private ContainerInterface $container;
    private Reader $annotationReader;

    public function __construct(ContainerInterface $container, Reader $annotationReader)
    {
        $this->container = $container;
        $this->annotationReader = $annotationReader;
    }

    public function describe(Swagger $api, Route $route, \ReflectionMethod $reflectionMethod)
    {
        if (!$annotation = $this->annotationReader->getMethodAnnotation($reflectionMethod, ListResponse::class)) {
            $annotation = $this->annotationReader->getMethodAnnotation($reflectionMethod, ViewResponse::class);
        }

        /** @var ListResponse|ViewResponse|null $annotation */
        if (!$annotation) {
            return;
        }

        $reflectionModelRegistry = new \ReflectionClass($this->modelRegistry);

        foreach ($this->getOperations($api, $route) as $operationAnnotation) {
            /** @var Response $response */
            foreach ($operationAnnotation->getResponses() as $identity => $response) {
                if ($identity >= 400) {
                    $errorSchema = new Schema(['type' => Type::BUILTIN_TYPE_ARRAY]);
                    $errorSchema->getItems()->setType(Type::BUILTIN_TYPE_STRING);
                    /** @var mixed $example */
                    $example = ['field:v1.error_code'];
                    $errorSchema->setExample($example);

                    $nullableResponseSchema = new Schema(['type' => Type::BUILTIN_TYPE_OBJECT, 'nullable' => true]);
                    $nullableResponseSchema->setExample(null);

                    $schema = $response->getSchema();
                    $schema->getProperties()->set('requestId', new Schema(['type' => Type::BUILTIN_TYPE_STRING]));
                    $schema->getProperties()->set('response', $nullableResponseSchema);
                    $schema->getProperties()->set('errors', $errorSchema);
                } else {
                    $groups = $annotation->groups ?? [];
                    $groups[] = self::PREFIX_INTERNAL_GROUPS.md5($route->serialize());

                    $options = [
                        'annotation' => $annotation,
                        'responseSchema' => $response,
                        'groups' => $groups,
                        'route' => $route,
                    ];
                    $model = new Model(new Type(Type::BUILTIN_TYPE_OBJECT, false, null, uniqid()), $groups, $options);

                    if ($annotation instanceof ListResponse && $annotation->pagination) {
                        $operationAnnotation->getParameters()->add(new Parameter([
                            'name' => 'limit',
                            'in' => 'query',
                            'description' => 'Pagination limit items per page',
                            'default' => 20,
                            'type' => 'integer',
                            'required' => true,
                        ]));

                        if ($annotation->paginationByLastValue) {
                            $operationAnnotation->getParameters()->add(new Parameter([
                                'name' => 'lastValue',
                                'in' => 'query',
                                'description' => 'Last viewed value cursor for pagination',
                                'default' => 0,
                                'type' => 'integer',
                                'required' => false,
                            ]));

                            if ($annotation->enableOrderBy) {
                                $operationAnnotation->getParameters()->add(new Parameter([
                                    'name' => 'orderBy',
                                    'in' => 'query',
                                    'description' => 'Order by field',
                                    'default' => 'id:DESC',
                                    'type' => Type::BUILTIN_TYPE_STRING,
                                    'required' => false,
                                ]));
                            }
                        } else {
                            $operationAnnotation->getParameters()->add(new Parameter([
                                'name' => 'page',
                                'in' => 'query',
                                'description' => 'Pagination page',
                                'default' => 1,
                                'type' => 'integer',
                                'required' => true,
                            ]));

                            if ($annotation->enableOrderBy) {
                                $operationAnnotation->getParameters()->add(new Parameter([
                                    'name' => 'orderBy',
                                    'in' => 'query',
                                    'description' => 'Order by field',
                                    'default' => 'id',
                                    'type' => Type::BUILTIN_TYPE_STRING,
                                    'required' => true,
                                ]));
                            }
                        }
                    }

                    $reflectionProperty = $reflectionModelRegistry->getProperty('names');
                    $reflectionProperty->setAccessible(true);
                    $names = $reflectionProperty->getValue($this->modelRegistry);
                    $parts = explode('\\', $annotation->entityClass);
                    $schemaModelName = end($parts);

                    $reflectonModel = new \ReflectionClass($model);

                    $propertyType = $reflectonModel->getProperty('type');
                    $propertyType->setAccessible(true);
                    $propertyType->setValue($model, new Type(Type::BUILTIN_TYPE_OBJECT));

                    $prefixSchemaModelName = $annotation instanceof ListResponse ? 'List' : 'View';

                    $names[$model->getHash()] = 'Response'.$prefixSchemaModelName.$schemaModelName;
                    $reflectionProperty->setValue($this->modelRegistry, $names);

                    $generatedRef = $this->modelRegistry->register($model);
                    $response->getSchema()->setRef($generatedRef);
                }
            }

            if ($annotation->security) {
                $unauthorizedSchema = new Schema([]);
                $unauthorizedSchema->getProperties()->set(
                    'error',
                    new Schema(['type' => Type::BUILTIN_TYPE_STRING])
                )->set(
                    'error_description',
                    new Schema(['type' => Type::BUILTIN_TYPE_STRING])
                );
                $unauthorizedResponse = new Response();
                $unauthorizedResponse->merge([
                    'schema' => [
                        'properties' => [
                            'error' => ['type' => Type::BUILTIN_TYPE_STRING],
                            'error_description' => ['type' => Type::BUILTIN_TYPE_STRING],
                        ],
                    ],
                    'description' => 'OAuth2 error unauthorized',
                ]);
                $operationAnnotation->getResponses()->set(401, $unauthorizedResponse);
            }

            $errorResponses = [];
            foreach ($annotation->errorCodesMap as list($responseCode, $errorCode, $description)) {
                $errorResponses[$responseCode] ??= [];
                $errorResponses[$responseCode][] = ['errorCode' => $errorCode, 'description' => $description];
            }

            foreach ($errorResponses as $responseCode => $errorResponseVariants) {
                $examples = [];
                foreach ($errorResponseVariants as $errorResponseVariant) {
                    $examples[$errorResponseVariant['description']] = [
                        'requestId' => 'bbfe8bb0-105c-479f-87a9-e78b0c860ccf',
                        'errors' => [$errorResponseVariant['errorCode']],
                        'response' => new stdClass(),
                    ];
                }

                $errorResponse = new Response([
                    'examples' => $examples,
                    'description' => 'Response '.$responseCode,
                    'schema' => [
                        'properties' => [
                            'errors' => [
                                'type' => Type::BUILTIN_TYPE_ARRAY,
                                'items' => [
                                    'type' => Type::BUILTIN_TYPE_STRING
                                ]
                            ],
                            'response' => [
                                'type' => Type::BUILTIN_TYPE_OBJECT,
                                'properties' => [],
                            ],
                            'requestId' => [
                                'type' => Type::BUILTIN_TYPE_STRING,
                            ]
                        ]
                    ]
                ]);

                $operationAnnotation->getResponses()->set($responseCode, $errorResponse);
            }
        }
    }
}
