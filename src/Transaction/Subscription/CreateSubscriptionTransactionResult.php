<?php

namespace App\Transaction\Subscription;

use App\Entity\Subscription\Subscription;
use Stripe\Price;
use Stripe\Product;

class CreateSubscriptionTransactionResult
{
    public ?Product $product = null;
    public ?Price $price = null;
    public ?Subscription $subscription = null;
}
