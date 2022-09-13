<?php

namespace App\DTO\V1\Subscription;

use Swagger\Annotations as SWG;
use App\Service\SubscriptionService;

class PaymentStatusResponse
{
    /**
     * @SWG\Property(
     *     enum={
     *         SubscriptionService::STATUS_PENDING,
     *         SubscriptionService::STATUS_UNPAID,
     *         SubscriptionService::STATUS_CONFIRMED
     *     }
     * )
     */
    public string $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }
}
