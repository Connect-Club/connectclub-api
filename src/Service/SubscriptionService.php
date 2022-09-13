<?php

namespace App\Service;

use App\Controller\ErrorCode;
use App\DTO\V1\Subscription\CreateRequest;
use App\DTO\V1\Subscription\UpdateRequest;
use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\Subscription\PaidSubscriptionRepository;
use App\Service\Transaction\TransactionManager;
use App\Transaction\FlushEntityManagerTransaction;
use App\Transaction\FlushRemoveManagerTransaction;
use App\Transaction\Subscription\CreatePriceTransaction;
use App\Transaction\Subscription\CreateProductTransaction;
use App\Transaction\Subscription\CreateCustomerForUserTransaction;
use App\Transaction\Subscription\CreateSubscriptionTransaction;
use App\Transaction\Subscription\CreateSubscriptionTransactionResult;
use App\Transaction\Subscription\DeactivatePriceTransaction;
use App\Transaction\Subscription\DeactivateProductTransaction;
use App\Transaction\Subscription\UpdateProductTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SubscriptionService
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNPAID = 'unpaid';

    private TransactionManager $transactionManager;
    private StripeSubscriptionService $stripeSubscriptionService;
    private EntityManagerInterface $entityManager;
    private PaidSubscriptionRepository $paidSubscriptionRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionManager $transactionManager,
        StripeSubscriptionService $stripeSubscriptionService,
        PaidSubscriptionRepository $paidSubscriptionRepository
    ) {
        $this->entityManager = $entityManager;
        $this->transactionManager = $transactionManager;
        $this->stripeSubscriptionService = $stripeSubscriptionService;
        $this->paidSubscriptionRepository = $paidSubscriptionRepository;
    }

    public function deleteSubscription(Subscription $subscription)
    {
        $this->transactionManager
            ->addTransaction(new DeactivatePriceTransaction(
                $this->stripeSubscriptionService,
                $subscription->stripePriceId
            ))
            ->addTransaction(new DeactivateProductTransaction(
                $this->stripeSubscriptionService,
                $subscription->stripeId
            ))
            ->addTransaction(new FlushRemoveManagerTransaction($this->entityManager, $subscription))
            ->run();
    }

    public function updateSubscription(Subscription $subscription, UpdateRequest $updateRequest)
    {
        if ($subscription->name == $updateRequest->name && $subscription->description == $updateRequest->description) {
            return;
        }

        $this->transactionManager
            ->addTransaction(new UpdateProductTransaction(
                $this->stripeSubscriptionService,
                $subscription,
                $updateRequest
            ))
            ->addTransaction(function () use ($subscription, $updateRequest) {
                $subscription->name = $updateRequest->name;
                $subscription->isActive = $updateRequest->isActive;
                $subscription->description = $updateRequest->description;
            })
            ->addTransaction(new FlushEntityManagerTransaction(
                $this->entityManager,
                $subscription
            ))
            ->run();
    }

    public function createSubscription(
        CreateRequest $createRequest,
        User $currentUser
    ): Subscription {
        $transactionResult = new CreateSubscriptionTransactionResult();

        $this->transactionManager
            ->addTransaction(new CreateProductTransaction(
                $this->stripeSubscriptionService,
                $createRequest,
                $transactionResult
            ))
            ->addTransaction(new CreatePriceTransaction(
                $this->stripeSubscriptionService,
                $createRequest,
                $transactionResult
            ))
            ->addTransaction(new CreateSubscriptionTransaction(
                $this->entityManager,
                $createRequest,
                $currentUser,
                $transactionResult
            ))
            ->run();

        return $transactionResult->subscription;
    }

    public function buySubscription(Subscription $subscription, User $user): \Stripe\Subscription
    {
        if ($user->stripeCustomerId === null) {
            $this->transactionManager
                ->addTransaction(new CreateCustomerForUserTransaction($this->stripeSubscriptionService, $user))
                ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $user))
                ->run();
        }

        if (!$this->paidSubscriptionRepository->findForUser($subscription, $user)) {
            $paidSubscription = new PaidSubscription($user, $subscription, PaidSubscription::STATUS_INCOMPLETE);
            $this->transactionManager
                ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $paidSubscription));
        }

        $stripeSubscription = null;

        $this->transactionManager
            ->addTransaction(function () use ($subscription, $user, &$stripeSubscription) {
                $stripeSubscription = $this->stripeSubscriptionService->createSubscription($subscription, $user);
            });

        $this->transactionManager->run();

        return $stripeSubscription;
    }

    public function markWaitingForPaymentConfirmation(Subscription $subscription, User $subscriber): void
    {
        $paidSubscription = $this->paidSubscriptionRepository->findForUser($subscription, $subscriber);

        if (!$paidSubscription) {
            throw new ApiException(ErrorCode::V1_SUBSCRIPTION_BUY_SUBSCRIPTION_FIRSTLY);
        }

        if ($this->canWaitForPaymentConfirmation($paidSubscription)) {
            $paidSubscription->waitingForPaymentConfirmationUpTo = time()
                + PaidSubscription::SECONDS_TO_CONFIRM_PAYMENT;
            $this->entityManager->flush();
        }
    }

    public function getPaymentStatus(Subscription $subscription, User $subscriber): string
    {
        $paidSubscription = $this->paidSubscriptionRepository->findForUser($subscription, $subscriber);
        if (!$paidSubscription) {
            return self::STATUS_UNPAID;
        }

        if (in_array($paidSubscription->status, PaidSubscription::getActiveStatuses())) {
            return self::STATUS_CONFIRMED;
        }

        if ($this->isWaitingForConfirmation($paidSubscription)) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_UNPAID;
    }

    public function isConfirmationOutdated(PaidSubscription $paidSubscription): bool
    {
        if (in_array($paidSubscription->status, PaidSubscription::getActiveStatuses())) {
            return false;
        }

        $waitingUpTo = $paidSubscription->waitingForPaymentConfirmationUpTo;
        if ($paidSubscription->status === PaidSubscription::STATUS_INCOMPLETE) {
            return $waitingUpTo < time();
        }

        return true;
    }

    private function isWaitingForConfirmation(PaidSubscription $paidSubscription): bool
    {
        return $paidSubscription->status === PaidSubscription::STATUS_INCOMPLETE
            && $paidSubscription->waitingForPaymentConfirmationUpTo !== null
            && $paidSubscription->waitingForPaymentConfirmationUpTo > time();
    }

    private function canWaitForPaymentConfirmation(PaidSubscription $paidSubscription): bool
    {
        return $paidSubscription->status === PaidSubscription::STATUS_INCOMPLETE
            && $paidSubscription->waitingForPaymentConfirmationUpTo === null;
    }
}
