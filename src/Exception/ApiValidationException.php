<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

class ApiValidationException extends ApiException
{
    private ConstraintViolationListInterface $constraintViolationList;

    public function __construct(
        ConstraintViolationListInterface $constraintViolationList,
        int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        Throwable $previous = null,
        array $headers = [],
        ?int $code = 0
    ) {
        $this->constraintViolationList = $constraintViolationList;

        parent::__construct('', $statusCode, $previous, $headers, $code);
    }

    public function getConstraintViolationList(): ConstraintViolationListInterface
    {
        return $this->constraintViolationList;
    }
}
