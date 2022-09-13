<?php

namespace App\Transaction\Subscription;

use App\DTO\V1\Subscription\UpdateRequest;
use App\Entity\Subscription\Subscription;
use App\Service\StripeSubscriptionService;
use App\Service\Transaction\Transaction;

class UpdateProductTransaction implements Transaction
{
    private UpdateRequest $updateRequest;
    private StripeSubscriptionService $stripeSubscriptionService;

    private ?Subscription $originalSubscription;

    public function __construct(
        StripeSubscriptionService $stripeSubscriptionService,
        Subscription $subscriptionBeforeUpdate,
        UpdateRequest $updateRequest
    ) {
        $this->updateRequest = $updateRequest;
        $this->originalSubscription = clone $subscriptionBeforeUpdate;
        $this->stripeSubscriptionService = $stripeSubscriptionService;
    }

    public function up(): void
    {
        $this->stripeSubscriptionService->updateProduct($this->originalSubscription->stripeId, $this->updateRequest);
    }

    public function down(): void
    {
        $originalSubscription = $this->originalSubscription;

        $revertRequest = new UpdateRequest();
        $revertRequest->name = $originalSubscription->name;
        $revertRequest->description = $originalSubscription->description;
        $revertRequest->isActive = $originalSubscription->isActive;

        $this->stripeSubscriptionService->updateProduct($this->originalSubscription->stripeId, $revertRequest);
    }
}
