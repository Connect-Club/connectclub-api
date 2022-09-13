<?php

namespace App\Service;

use App\Controller\ErrorCode;
use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Payment;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Repository\Subscription\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\LineItem;
use Stripe\PaymentIntent;
use Stripe\StripeObject;
use Stripe\SubscriptionItem;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Stripe\Subscription as StripeSubscription;

class SubscriptionWebhookService
{
    private string $webhookSecret;
    private string $backendName;

    private UserRepository $userRepository;
    private SubscriptionRepository $subscriptionRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        string $webhookSecret,
        string $backendName,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->webhookSecret = $webhookSecret;
        $this->backendName = $backendName;
        $this->entityManager = $entityManager;
    }

    public function handleEvent(Request $request): void
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get('stripe-signature'),
                $this->webhookSecret
            );

            if (!$this->checkObjectBackend($event->data->object)) {
                return;
            }

            switch ($event->type) {
                case Event::CUSTOMER_SUBSCRIPTION_UPDATED:
                case Event::CUSTOMER_SUBSCRIPTION_CREATED:
                case Event::CUSTOMER_SUBSCRIPTION_DELETED:
                    $this->handleSubscriptionEvent($event);
                    break;
                case Event::INVOICE_PAID:
                    $this->handleInvoicePaid($event);
                    break;
            }
        } catch (\UnexpectedValueException $exception) {
            throw new BadRequestHttpException(ErrorCode::V1_STRIPE_INVALID_PAYLOAD);
        } catch (SignatureVerificationException $exception) {
            throw new BadRequestHttpException(ErrorCode::V1_STRIPE_INVALID_SIGNATURE);
        }
    }

    private function handleInvoicePaid(Event $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->data->object;

        $subscriber = $this->userRepository->findOneBy([
            'stripeCustomerId' => $invoice->customer,
        ]);

        $paidSubscriptions = $this->findPaidSubscriptions($subscriber);

        foreach ($this->findSubscriptionsByStripeInvoice($invoice) as $subscription) {
            $this->entityManager->persist(new Payment(
                $invoice->id,
                $invoice->amount_paid,
                $paidSubscriptions[$subscription->id->toString()],
                $invoice->status_transitions->paid_at
            ));
        }

        $this->entityManager->flush();
    }

    private function handleSubscriptionEvent(Event $event): void
    {
        /** @var StripeSubscription $stripeSubscription */
        $stripeSubscription = $event->data->object;

        $this->updatePaidSubscriptionStatuses($stripeSubscription);
    }

    /**
     * @return Subscription[]
     */
    private function findSubscriptionsByStripeSubscription(StripeSubscription $stripeSubscription): array
    {
        $stripeSubscriptionIds = [];
        /** @var SubscriptionItem $item */
        foreach ($stripeSubscription->items->data as $item) {
            $stripeSubscriptionIds[] = $item->price->product;
        }

        return $this->subscriptionRepository->findBy(['stripeId' => $stripeSubscriptionIds]);
    }

    /**
     * @return Subscription[]
     */
    private function findSubscriptionsByStripeInvoice(Invoice $invoice): array
    {
        $stripeSubscriptionIds = [];
        /** @var LineItem $item */
        foreach ($invoice->lines->data as $item) {
            $stripeSubscriptionIds[] = $item->price->product;
        }

        return $this->subscriptionRepository->findBy(['stripeId' => $stripeSubscriptionIds]);
    }

    private function updatePaidSubscriptionStatuses(StripeSubscription $stripeSubscription): void
    {
        $subscriber = $this->findSubscriberByStripeSubscription($stripeSubscription);

        $paidSubscriptions = $this->findPaidSubscriptions($subscriber);
        foreach ($this->findSubscriptionsByStripeSubscription($stripeSubscription) as $subscription) {
            $status = $this->convertStripeStatus($stripeSubscription->status);
            if (!$status) {
                continue;
            }

            $paidSubscriptions[$subscription->id->toString()]->status = $status;
        }

        $this->entityManager->flush();
    }

    private function findSubscriberByStripeSubscription(StripeSubscription $stripeSubscription): ?User
    {
        if (!$stripeSubscription->customer) {
            return null;
        }

        return $this->userRepository->findOneBy([
            'stripeCustomerId' => $stripeSubscription->customer,
        ]);
    }

    /**
     * @return PaidSubscription[]
     */
    private function findPaidSubscriptions(User $subscriber): array
    {
        $indexedSubscriptions = [];
        foreach ($subscriber->paidSubscriptions as $paidSubscription) {
            $indexedSubscriptions[$paidSubscription->subscription->id->toString()] = $paidSubscription;
        }

        return $indexedSubscriptions;
    }

    private function checkObjectBackend(StripeObject $object): bool
    {
        $objectBackendName = $this->getObjectBackend($object);

        return $objectBackendName === $this->backendName;
    }

    private function getObjectBackend(StripeObject $object): string
    {
        if (isset($object->metadata) && isset($object->metadata['backend'])) {
            return $object->metadata['backend'];
        } elseif ($object instanceof Invoice) {
            /** @var LineItem $datum */
            foreach ($object->lines->data as $datum) {
                if (isset($datum->price->metadata) && isset($datum->price->metadata['backend'])) {
                    return $datum->price->metadata['backend'];
                }
            }
        }

        return '';
    }

    private function convertStripeStatus(string $status): ?int
    {
        $stripeToDb = [
            StripeSubscription::STATUS_ACTIVE => PaidSubscription::STATUS_ACTIVE,
            StripeSubscription::STATUS_PAST_DUE => PaidSubscription::STATUS_PAST_DUE,
            StripeSubscription::STATUS_UNPAID => PaidSubscription::STATUS_UNPAID,
            StripeSubscription::STATUS_CANCELED => PaidSubscription::STATUS_CANCELED,
            StripeSubscription::STATUS_INCOMPLETE => PaidSubscription::STATUS_INCOMPLETE,
            StripeSubscription::STATUS_INCOMPLETE_EXPIRED => PaidSubscription::STATUS_INCOMPLETE_EXPIRED,
            StripeSubscription::STATUS_TRIALING => PaidSubscription::STATUS_TRIALING,
        ];

        return $stripeToDb[$status] ?? null;
    }
}
