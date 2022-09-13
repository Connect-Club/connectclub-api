<?php

namespace App\Transaction\Subscription;

use App\Entity\User;
use App\Service\StripeSubscriptionService;
use App\Service\Transaction\Transaction;

class CreateCustomerForUserTransaction implements Transaction
{
    private User $user;
    private StripeSubscriptionService $stripeSubscriptionService;

    public function __construct(StripeSubscriptionService $stripeSubscriptionService, User $user)
    {
        $this->stripeSubscriptionService = $stripeSubscriptionService;
        $this->user = $user;
    }

    public function up(): void
    {
        $customer = $this->stripeSubscriptionService->createCustomer($this->user);

        $this->user->stripeCustomerId = $customer->id;
    }

    public function down(): void
    {
        if (!$this->user->stripeCustomerId) {
            return;
        }

        $this->stripeSubscriptionService->deleteCustomer($this->user->stripeCustomerId);
    }
}
