<?php

namespace App\Controller;

use Anboo\ApiBundle\Swagger\ApiResponse;
use App\DebugApiResponse;
use App\Entity\User;
use App\Exception\ApiValidationException;
use App\Filter\BaseFilter;
use App\Filter\FilterInterface;
use App\Service\PaginationQueryPreProcessor;
use App\Service\Sanitizer;
use Codeception\Util\Debug;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BaseController extends AbstractController
{
    protected SerializerInterface $serializer;
    protected ValidatorInterface $validator;
    protected Sanitizer $sanitizer;
    /** @var iterable|BaseFilter[] */
    protected iterable $filters;
    private LoggerInterface $logger;
    private ?Request $predefinedMasterRequest = null;

    public function setFilters(iterable $filters)
    {
        $this->filters = $filters;
    }

    public function setPredefinedMasterRequest(?Request $predefinedMasterRequest): self
    {
        $this->predefinedMasterRequest = $predefinedMasterRequest;

        return $this;
    }

    /**
     * @required
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @required
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @required
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @required
     */
    public function setSanitizer(Sanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * @param mixed $data
     * @param int   $status
     *
     * @return JsonResponse
     */
    protected function handleResponse(
        $data,
        $status = Response::HTTP_OK,
        array $serializationGroups = [],
        bool $asObject = false
    ) {
        return $this->createJsonResponse(
            ApiResponse::createSuccessfullyResponse(
                $this->getRequestId(),
                is_array($data) && empty($data) && $asObject ? new \stdClass() : $data
            ),
            $status,
            $serializationGroups
        );
    }

    /**
     * @param mixed $data
     * @param bool  $enableMaxDepth
     *
     * @return string
     */
    protected function getBody($data, array $serializationGroups = [], $enableMaxDepth = false)
    {
        $serializationGroups = $this->processSerializationGroups($serializationGroups);

        return null !== $data ? $this->serialize($data, (array) $serializationGroups, $enableMaxDepth) : '';
    }

    /**
     * @param mixed $data                Data
     * @param array $serializationGroups Context
     * @param bool  $enableMaxDepth
     *
     * @return string
     */
    protected function serialize($data, array $serializationGroups = [], $enableMaxDepth = false)
    {
        $serializationGroups = $this->processSerializationGroups($serializationGroups);
        $options = $serializationGroups ? ['groups' => $serializationGroups] : [];

        if ($enableMaxDepth) {
            $options['enable_max_depth'] = true;
        }

        $options['circular_reference_handler'] = function ($object) {
            if (method_exists($object, 'getId')) {
                return [
                    'id' => $object->getId(),
                ];
            } else {
                return [];
            }
        };

        return $this->serializer->serialize($data, 'json', $options);
    }

    /**
     * @param array $serializationGroups
     *
     * @return mixed
     */
    protected function processSerializationGroups($serializationGroups)
    {
        if ($serializationGroups) {
            $serializationGroups[] = 'default';
        }

        return array_unique($serializationGroups);
    }

    /**
     * @param string      $entityClass
     * @param object|null $entityObject
     * @param array       $groups
     *
     * @return object
     */
    public function getEntityFromRequestTo(Request $request, $entityClass, $entityObject = null, $groups = [])
    {
        if ($groups) {
            $serializerContext = ['groups' => $groups];
        } else {
            $serializerContext = [];
        }

        if ($entityObject) {
            $serializerContext['object_to_populate'] = $entityObject;
        }

        $content = $request->getContent();
        $content = !empty($content) ? $content : '{}';

        try {
            return $this->serializer->deserialize($content, $entityClass, 'json', $serializerContext);
        } catch (ExceptionInterface $exception) {
            throw new BadRequestHttpException();
        } catch (\Throwable $serializerException) {
            $this->logger->error($serializerException->getMessage().' '.$content, [
                'exception' => $serializerException
            ]);

            throw $serializerException;
        }
    }

    public function validate(object $entity, ?array $constraints = null): ConstraintViolationListInterface
    {
        return $this->validator->validate($entity, $constraints);
    }

    public function unprocessableUnlessValid($entity, ?array $constraints = null): void
    {
        $errors = $this->validate($entity, $constraints);
        if ($errors->count() > 0) {
            throw new ApiValidationException($errors);
        }
    }

    public function handleErrorResponse(
        ConstraintViolationListInterface $constraintViolationList,
        int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY
    ): JsonResponse {
        return $this->createErrorResponse($this->formatConstraintViolationList($constraintViolationList), $statusCode);
    }

    public function formatConstraintViolationList(ConstraintViolationListInterface $constraintViolationList): array
    {
        $formatErrors = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($constraintViolationList as $violation) {
            $formatErrors[] = $violation->getPropertyPath().':'.$violation->getMessage();
        }

        return $formatErrors;
    }

    /**
     * @param string|array $errors
     */
    public function createErrorResponse(
        $errors,
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $trace = []
    ): JsonResponse {
        $errors = is_array($errors) ? $errors : [$errors];

        return $this->createJsonResponse(
            ApiResponse::createErrorResponse($this->getRequestId(), $errors),
            $code,
            [],
            $this->isProd() ? null : $trace
        );
    }

    /**
     * @param string|array $errors
     */
    public function createErrorResponseWithData(
        $errors,
        array $data,
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY
    ): JsonResponse {
        $errors = is_array($errors) ? $errors : [$errors];

        return $this->createJsonResponse(
            ApiResponse::createErrorResponse($this->getRequestId(), $errors, $data),
            $code,
            []
        );
    }

    public function createJsonResponse(
        ApiResponse $apiResponse,
        int $code = Response::HTTP_OK,
        array $serializationGroups = [],
        ?array $trace = null
    ): JsonResponse {
        $data = [
            'response' => $apiResponse->getData(),
            'errors' => $apiResponse->getErrors() ?? [],
            'requestId' => $this->getRequestId(),
        ];

        $prettyPrint = false;
        if ($trace !== null) {
            $data['trace'] = array_walk($trace, function ($traceItem) {
                if (isset($traceItem['args']) && is_array($traceItem['args'])) {
                    foreach ($traceItem['args'] as &$arg) {
                        $type = gettype($arg);

                        $arg = $type === 'object' ? get_class($arg) : $type;
                    }
                }
            });
            $prettyPrint = true;
        }

        $body = $this->serialize($data, $serializationGroups);

        $response = new JsonResponse($body, $code, [], true);
        if ($prettyPrint) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
        }

        return $response;
    }

    public function getList(QueryBuilder $builder, Request $request): ArrayCollection
    {
        $page = $request->query->getInt('page') ?? 1;
        $limit = $request->query->getInt('limit') ?? 20;
        $orderBy = $request->query->get('orderBy') ?? 'id';

        list($orderBy, $order) = $this->getOrderBy($orderBy);

        $rootEntity = $builder->getRootEntities()[0];
        $metadata = $builder->getEntityManager()->getClassMetadata($rootEntity);
        try {
            $metadata->getFieldMapping($orderBy);
        } catch (MappingException $mappingException) {
            $orderBy = $metadata->getIdentifier()[0];
        }

        $builder
            ->setFirstResult($limit * ($page - 1))
            ->setMaxResults($limit)
            ->orderBy('e.'.$orderBy, $order)
        ;

        $joins = $builder->getDQLPart('join');
        $paginator = new Paginator($builder->getQuery(), !empty($joins));

        return new ArrayCollection(iterator_to_array($paginator->getIterator()));
    }

    public function paginateByLastCursor(
        QueryBuilder $builder,
        Request $request,
        string $cursorField = null,
        string $order = null,
        array $orderByFieldAliases = [],
        PaginationQueryPreProcessor $paginationQueryPreProcessor = null,
        bool $isWindowedFunction = false
    ) {
        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : 20;
        $lastValue = $request->query->get('lastValue');

        if ($request->query->get('orderBy')) {
            $orderBy = $request->query->get('orderBy') ?? 'id';
            list($cursorField, $order) = $this->getOrderBy($orderBy);
        }

        if (isset($orderByFieldAliases[$cursorField])) {
            $dqlCursorField = $orderByFieldAliases[$cursorField];
            $alias = true;
        } else {
            $dqlCursorField = 'e.'.$cursorField;
            $alias = false;
        }

        $cursorModifier = 'ASC' == strtoupper($order) ? '>' : '<';

        $lastValue = $alias || $isWindowedFunction ?
            $lastValue :
            $this->sanitizer->sanitize($builder, $dqlCursorField, $lastValue);

        $query = $builder->setMaxResults($limit)->orderBy($dqlCursorField, $order);
        if ($lastValue) {
            $query
                ->select('e')
                ->andWhere(sprintf('%s %s :lastValue', $dqlCursorField, $cursorModifier))
                ->setParameter('lastValue', $lastValue)
            ;
        }

        $paginatorQuery = $builder->getQuery();
        if ($paginationQueryPreProcessor) {
            $paginatorQuery = $paginationQueryPreProcessor->process($paginatorQuery);
        }

        $joins = $builder->getDQLPart('join');
        $paginator = new Paginator($paginatorQuery, !empty($joins));

        $items = iterator_to_array($paginator->getIterator());

        $builderCount = clone $builder;
        $countQuery = 'ASC' == $order ?
            $builderCount->select('MAX('.$dqlCursorField.')') :
            $builderCount->select('MIN('.$dqlCursorField.')');

        $countQuery = $countQuery
                        ->resetDQLPart('orderBy')
                        ->setMaxResults(null)
                        ->setFirstResult(null)
                        ->getQuery();

        if ($paginationQueryPreProcessor) {
            $countQuery = $paginationQueryPreProcessor->process($countQuery);
        }

        $limitValue = $countQuery->getSingleScalarResult();

        if ($items) {
            $pa = PropertyAccess::createPropertyAccessor();
            if ($alias) {
                list ($aliasRootEntityAlias, $aliasFieldName) = explode('.', $dqlCursorField);
                /** @var Join $join */
                foreach ($joins['e'] as $join) {
                    if ($aliasRootEntityAlias == $join->getAlias()) {
                        list(, $fieldFromRootEntity) = explode('.', $join->getJoin());
                        $propertyPath = $fieldFromRootEntity.'.'.$aliasFieldName;
                        $lastValue = $pa->getValue($items[count($items) - 1], $propertyPath);
                    }
                }
            } else {
                $lastValue = $pa->getValue($items[count($items) - 1], $cursorField);
            }
            if ($lastValue == $limitValue) {
                $lastValue = null;
            }
        } else {
            $lastValue = null;
        }

        return [$items, $lastValue, $limitValue];
    }

    public function handleFilters(string $entityClass, Request $request, QueryBuilder $qb, string $mainAlias = 'e')
    {
        $filterRequest = json_decode($request->query->get('filter'), true) ?? [];

        $this->handleArrayFilters($entityClass, $filterRequest, $qb, $mainAlias);
    }

    public function handleArrayFilters(
        string $entityClass,
        array $filterRequest,
        QueryBuilder $qb,
        string $mainAlias = 'e'
    ) {
        foreach ($this->filters as $filter) {
            $filter->setQueryBuilder($qb);
            if ($filter->support($entityClass, $filterRequest)) {
                $filter->handle($qb, $entityClass, $mainAlias, $filterRequest);
            }
        }
    }

    protected function getRequestId(): string
    {
        $request = $this->predefinedMasterRequest ?? $this->get('request_stack')->getCurrentRequest();

        if ($request) {
            return $request->attributes->get('request_id') ?? '';
        }

        return '';
    }

    private function getOrderBy(string $orderBy): array
    {
        $orderData = explode(':', $orderBy);

        if (isset($orderData[1])) {
            list($orderBy, $order) = $orderData;
            $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? $order : 'ASC';
        } else {
            $order = 'ASC';
        }

        return [$orderBy, strtoupper($order)];
    }

    private function isProd(): bool
    {
        return !isset($_ENV['APP_ENV']) || !in_array($_ENV['APP_ENV'], ['dev', 'codeception']);
    }

    /**
     * @noinspection PhpRedundantMethodOverrideInspection
     */
    protected function getUser(): ?User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::getUser();
    }
}
