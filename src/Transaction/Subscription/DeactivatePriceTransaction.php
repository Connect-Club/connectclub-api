<?php

namespace App\Transaction\Subscription;

use App\Service\StripeSubscriptionService;
use App\Service\Transaction\Transaction;

class DeactivatePriceTransaction implements Transaction
{
    private StripeSubscriptionService $stripeSubscriptionService;
    private string $priceId;

    public function __construct(StripeSubscriptionService $stripeSubscriptionService, string $priceId)
    {
        $this->stripeSubscriptionService = $stripeSubscriptionService;
        $this->priceId = $priceId;
    }

    public function up(): void
    {
        $this->stripeSubscriptionService->deactivatePrice($this->priceId);
    }

    public function down(): void
    {
        $this->stripeSubscriptionService->activatePrice($this->priceId);
    }
}
