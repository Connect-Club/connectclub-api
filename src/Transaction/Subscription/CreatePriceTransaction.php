<?php

namespace App\Transaction\Subscription;

use App\DTO\V1\Subscription\CreateRequest;
use App\Service\StripeSubscriptionService;
use App\Service\Transaction\Transaction;

class CreatePriceTransaction implements Transaction
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
        if (!$this->result->product) {
            throw new \RuntimeException('Product must be created already');
        }

        $this->result->price = $this->subscriptionService->createPrice(
            $this->createRequest,
            $this->result->product
        );
    }

    public function down(): void
    {
        if (!$this->result->price) {
            return;
        }

        $this->subscriptionService->deactivatePrice($this->result->price->id);
    }
}
