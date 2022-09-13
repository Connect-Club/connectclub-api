<?php

namespace App\DTO\V1\Subscription;

use Stripe\Subscription;

class BuyResponse
{
    public string $publicKey;
    public string $clientSecret;

    public function __construct(Subscription $stripeSubscription, string $publicKey)
    {
        $this->clientSecret = $stripeSubscription->latest_invoice->payment_intent->client_secret;
        $this->publicKey = $publicKey;
    }
}
