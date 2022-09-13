<?php

namespace App\Transaction\Subscription;

use App\DTO\V1\Subscription\CreateRequest;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Service\Transaction\CommittableTransaction;
use App\Service\Transaction\Transaction;
use Doctrine\ORM\EntityManagerInterface;

class CreateSubscriptionTransaction implements Transaction, CommittableTransaction
{
    private EntityManagerInterface $entityManager;
    private CreateRequest $createRequest;
    private CreateSubscriptionTransactionResult $result;
    private User $user;

    public function __construct(
        EntityManagerInterface $entityManager,
        CreateRequest $createRequest,
        User $user,
        CreateSubscriptionTransactionResult $transactionResult
    ) {
        $this->entityManager = $entityManager;
        $this->createRequest = $createRequest;
        $this->result = $transactionResult;
        $this->user = $user;
    }

    public function up(): void
    {
        if (!$this->result->product) {
            throw new \RuntimeException('Product must be already created');
        }

        if (!$this->result->price) {
            throw new \RuntimeException('Price must be already created');
        }

        $createRequest = $this->createRequest;

        $subscription = new Subscription(
            $createRequest->name,
            $createRequest->price,
            $this->result->product->id,
            $this->result->price->id,
            $this->user
        );
        $subscription->isActive = $createRequest->isActive;
        $subscription->description = $createRequest->description;

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->persist($subscription);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $e;
        }

        $this->result->subscription = $subscription;
    }

    public function down(): void
    {
        $this->entityManager->rollback();
    }

    public function commit()
    {
        $this->entityManager->commit();
    }
}
