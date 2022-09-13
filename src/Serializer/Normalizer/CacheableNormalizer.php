<?php

namespace App\Serializer\Normalizer;

use App\Serializer\CacheableNormalizerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class CacheableNormalizer implements NormalizerInterface
{
    private ObjectNormalizer $normalizer;
    private Security $security;
    private CacheInterface $cache;

    public function __construct(ObjectNormalizer $normalizer, Security $security, CacheInterface $cache)
    {
        $this->normalizer = $normalizer;
        $this->security = $security;
        $this->cache = $cache;
    }

    public function normalize($object, $format = null, array $context = []): array
    {
        /** @var CacheableNormalizerInterface $object */
        try {
            $data = $this->cache->get(
                $object->generateCacheCode(),
                (function (ItemInterface $item) use ($object, $format, $context) {
                    if ($tags = $object->provideTags()) {
                        $item->tag($tags);
                    }

                    $item->expiresAt($object->provideExpiresAt());

                    return $this->normalizer->normalize($object, $format, $context);
                })->bindTo($this)
            );
        } catch (Throwable $e) {
            return $this->normalizer->normalize($object, $format, $context);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof CacheableNormalizerInterface;
    }
}
