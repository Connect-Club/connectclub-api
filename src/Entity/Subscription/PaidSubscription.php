<?php

namespace App\Entity\Subscription;

use App\Repository\Subscription\PaidSubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

/**
 * @ORM\Entity(repositoryClass=PaidSubscriptionRepository::class)
 * @ORM\Table(
 *     indexes={
 *         @ORM\Index(
 *             name="waiting_for_payment_confirmation_up_to",
 *             columns={"waiting_for_payment_confirmation_up_to"},
 *             options={"where": "(waiting_for_payment_confirmation_up_to IS NOT NULL)"}
 *         )
 *     }
 * )
 */
class PaidSubscription
{
    const STATUS_INCOMPLETE = 1;
    const STATUS_INCOMPLETE_EXPIRED = 2;
    const STATUS_TRIALING = 3;
    const STATUS_ACTIVE = 4;
    const STATUS_PAST_DUE = 5;
    const STATUS_UNPAID = 6;
    const STATUS_CANCELED = 7;

    const SECONDS_TO_CONFIRM_PAYMENT = 300;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="paidSubscriptions")
     * @ORM\JoinColumn(nullable=false)
     */
    public User $subscriber;

    /**
     * @ORM\ManyToOne(targetEntity=Subscription::class, inversedBy="subscribers")
     * @ORM\JoinColumn(nullable=false)
     */
    public Subscription $subscription;

    /**
     * @ORM\Column(type="bigint")
     */
    public int $createdAt;

    /**
     * @ORM\Column(type="smallint")
     */
    public int $status;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    public ?int $waitingForPaymentConfirmationUpTo;

    /**
     * @var Collection|Payment[]
     * @ORM\OneToMany(targetEntity=Payment::class, mappedBy="paidSubscription")
     */
    private Collection $payments;

    public function __construct(User $subscriber, Subscription $subscription, int $status)
    {
        $this->subscriber = $subscriber;
        $this->subscription = $subscription;
        $this->status = $status;
        $this->createdAt = time();
        $this->payments = new ArrayCollection();
    }

    public static function getActiveStatuses(): array
    {
        return [
            self::STATUS_TRIALING,
            self::STATUS_ACTIVE,
            self::STATUS_PAST_DUE,
        ];
    }

    /**
     * @return Collection|Payment[]
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments[] = $payment;
            $payment->setPaidSubscription($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getPaidSubscription() === $this) {
                $payment->setPaidSubscription(null);
            }
        }

        return $this;
    }
}
