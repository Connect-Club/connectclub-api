<?php

namespace App\Tests\Fixture;

use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Payment;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;

abstract class SubscriptionPaymentFixture extends Fixture
{
    protected EntityManagerInterface $entityManager;

    protected function createPayments(
        User $subscriber,
        Subscription $subscription,
        DateTimeImmutable $firstPaidAt,
        int $monthsPaid,
        int $paidSubscriptionStatus = PaidSubscription::STATUS_ACTIVE
    ): void {
        $paidSubscription = new PaidSubscription($subscriber, $subscription, $paidSubscriptionStatus);
        $this->entityManager->persist($paidSubscription);

        for ($i = 0; $i < $monthsPaid; $i++) {
            $this->entityManager->persist(new Payment(
                "in_{$subscriber->email}_$i",
                $subscription->price,
                $paidSubscription,
                $firstPaidAt->add(new DateInterval("P{$i}M"))->getTimestamp()
            ));
        }
    }
}
