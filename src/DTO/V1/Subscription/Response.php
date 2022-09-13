<?php

namespace App\DTO\V1\Subscription;

use App\Entity\Subscription\Subscription;

class Response
{
    public string $id;
    public string $name;
    public ?string $description;
    public int $price;
    public bool $isActive;
    public int $createdAt;
    public int $authorId;

    public function __construct(Subscription $subscription)
    {
        $this->id = $subscription->id->toString();
        $this->name = $subscription->name;
        $this->description = $subscription->description;
        $this->price = $subscription->price;
        $this->isActive = $subscription->isActive;
        $this->createdAt = $subscription->createdAt;
        $this->authorId = $subscription->author->id;
    }
}
