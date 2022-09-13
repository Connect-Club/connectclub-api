<?php

namespace App\DTO\V1\Subscription;

class Summary
{
    public int $totalSalesCount;
    public int $totalSalesAmount;
    public int $activeSubscriptions;

    public function __construct(int $totalSalesCount, int $totalSalesAmount, int $activeSubscriptions)
    {
        $this->totalSalesCount = $totalSalesCount;
        $this->totalSalesAmount = $totalSalesAmount;
        $this->activeSubscriptions = $activeSubscriptions;
    }
}
