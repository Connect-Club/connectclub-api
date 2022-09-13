<?php

namespace App\Serializer;

use DateTimeInterface;

interface CacheableNormalizerInterface
{
    public function generateCacheCode(): string;
    public function provideTags(): ?array;
    public function provideExpiresAt(): DateTimeInterface;
}
