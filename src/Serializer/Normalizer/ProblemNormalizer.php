<?php

namespace App\Serializer\Normalizer;

use Anboo\ApiBundle\Swagger\ApiResponse;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProblemNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    private bool $debug;
    private RequestStack $requestStack;

    /**
     * ProblemNormalizer constructor.
     */
    public function __construct(bool $debug, RequestStack $requestStack)
    {
        $this->debug = $debug;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($exception, $format = null, array $context = [])
    {
        $debug = $this->debug && ($context['debug'] ?? true);

        if ($debug) {
            $response = [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ];
        } else {
            $response = ['Internal server error'];
        }

        $data = ApiResponse::createErrorResponse(
            $this->requestStack->getCurrentRequest()->attributes->get('request_id'),
            $response
        )->toArray();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof FlattenException;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
