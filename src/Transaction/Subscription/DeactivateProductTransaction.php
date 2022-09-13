<?php

namespace App\Transaction\Subscription;

use App\Service\StripeSubscriptionService;
use App\Service\Transaction\Transaction;

class DeactivateProductTransaction implements Transaction
{
    private StripeSubscriptionService $stripeSubscriptionService;
    private string $productId;

    public function __construct(StripeSubscriptionService $stripeSubscriptionService, string $productId)
    {
        $this->stripeSubscriptionService = $stripeSubscriptionService;
        $this->productId = $productId;
    }

    public function up(): void
    {
        $this->stripeSubscriptionService->deactivateProduct($this->productId);
    }

    public function down(): void
    {
        $this->stripeSubscriptionService->activateProduct($this->productId);
    }
}
