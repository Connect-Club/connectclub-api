<?php

namespace App\Doctrine\Listener;

use Symfony\Contracts\Cache\CacheInterface;

class CacheAwareListener
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}
