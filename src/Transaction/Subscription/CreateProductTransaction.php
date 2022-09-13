<?php

namespace App\Transaction\Subscription;

use App\DTO\V1\Subscription\CreateRequest;
use App\Service\StripeSubscriptionService;
use App\Service\Transaction\Transaction;

class CreateProductTransaction implements Transaction
{
    private StripeSubscriptionService $subscriptionService;
    private CreateRequest $createRequest;
    private CreateSubscriptionTransactionResult $result;

    public function __construct(
        StripeSubscriptionService $subscriptionService,
        CreateRequest $createRequest,
        CreateSubscriptionTransactionResult $transactionResult
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->createRequest = $createRequest;
        $this->result = $transactionResult;
    }

    public function up(): void
    {
        $this->result->product = $this->subscriptionService->createProduct($this->createRequest);
    }

    public function down(): void
    {
        if (!$this->result->product) {
            return;
        }

        $this->subscriptionService->deactivateProduct($this->result->product->id);
    }
}
