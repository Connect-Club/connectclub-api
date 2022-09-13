<?php

namespace App\Service;

use App\DTO\V1\Subscription\CreateRequest;
use App\DTO\V1\Subscription\UpdateRequest;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Stripe\Collection as StripeCollection;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class StripeSubscriptionService
{
    private StripeClient $stripeClient;
    private string $stripeBackendName;

    public function __construct(string $stripeBackendName, StripeClient $stripeClient)
    {
        $this->stripeBackendName = $stripeBackendName;
        $this->stripeClient = $stripeClient;
    }

    public function createProduct(CreateRequest $createRequest): Product
    {
        $productData = [
            'name' => $createRequest->name,
            'metadata' => [
                'backend' => $this->stripeBackendName,
            ],
        ];

        if ($createRequest->description) {
            $productData['description'] = $createRequest->description;
        }

        return $this->stripeClient->products->create($productData);
    }

    public function createPrice(CreateRequest $createRequest, Product $stripeProduct): Price
    {
        return $this->stripeClient->prices->create([
            'unit_amount' => $createRequest->price,
            'currency' => Subscription::CURRENCY,
            'product' => $stripeProduct->id,
            'recurring' => [
                'interval' => 'month',
            ],
            'metadata' => [
                'backend' => $this->stripeBackendName,
            ],
        ]);
    }

    public function updateProduct(string $productId, UpdateRequest $updateRequest): void
    {
        $this->stripeClient->products->update($productId, [
            'name' => $updateRequest->name,
            'description' => $updateRequest->description,
        ]);
    }

    public function deactivateProduct(string $productId): void
    {
        $this->stripeClient->products->update($productId, [
            'active' => false,
        ]);
    }

    public function deactivatePrice(string $priceId): void
    {
        $this->stripeClient->prices->update($priceId, [
            'active' => false,
        ]);
    }

    public function activateProduct(string $productId): void
    {
        $this->stripeClient->products->update($productId, [
            'active' => true,
        ]);
    }

    public function activatePrice(string $priceId): void
    {
        $this->stripeClient->prices->update($priceId, [
            'active' => true,
        ]);
    }

    public function createCustomer(User $user): Customer
    {
        $phone = $user->phone ? PhoneNumberUtil::getInstance()->format($user->phone, PhoneNumberFormat::E164) : null;

        return $this->stripeClient->customers->create([
            'email' => $user->email,
            'name' => $user->name . ' ' . $user->surname,
            'phone' => $phone,
            'metadata' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'backend' => $this->stripeBackendName,
            ],
        ], [
            'idempotency_key' => "{$this->stripeBackendName}:{$user->id}",
        ]);
    }

    public function createSubscription(Subscription $subscription, User $user): \Stripe\Subscription
    {
        return $this->stripeClient->subscriptions->create([
            'customer' => $user->stripeCustomerId,
            'payment_behavior' => 'default_incomplete',
            'items' => [
                [
                    'price' => $subscription->stripePriceId,
                ],
            ],
            'metadata' => [
                'backend' => $this->stripeBackendName,
                'username' => $user->username,
                'user_id' => $user->id,
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ], [
            'idempotency_key' => "{$this->stripeBackendName}:{$subscription->id->toString()}:{$user->id}",
        ]);
    }

    public function deleteCustomer(string $stripeCustomerId): void
    {
        $this->stripeClient->customers->delete($stripeCustomerId);
    }

    public function cancelSubscription(string $stripeSubscriptionId): void
    {
        $this->stripeClient->subscriptions->cancel($stripeSubscriptionId);
    }
}
