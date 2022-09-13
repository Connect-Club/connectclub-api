<?php

namespace App\Entity\Subscription;

use App\Repository\Subscription\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PaymentRepository::class)
 * @ORM\Table(
 *     name="subscription_payment",
 *     indexes={
 *         @ORM\Index(columns={"paid_at"}, name="idx_subscription_payment_paid_at"),
 *         @ORM\Index(columns={"paid_subscription_id"}, name="idx_subscription_payment_paid_subscription")
 *     }
 * )
 */
class Payment
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $stripeInvoiceId;

    /**
     * @ORM\Column(type="integer")
     */
    private int $amount;

    /**
     * @ORM\ManyToOne(targetEntity=PaidSubscription::class, inversedBy="payments")
     * @ORM\JoinColumn(nullable=false)
     */
    private PaidSubscription $paidSubscription;

    /**
     * @ORM\Column(type="bigint")
     */
    private int $paidAt;

    public function __construct(string $stripeInvoiceId, int $amount, PaidSubscription $paidSubscription, int $paidAt)
    {
        $this->stripeInvoiceId = $stripeInvoiceId;
        $this->amount = $amount;
        $this->paidSubscription = $paidSubscription;
        $this->paidAt = $paidAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStripeInvoiceId(): ?string
    {
        return $this->stripeInvoiceId;
    }

    public function setStripeInvoiceId(string $stripeInvoiceId): self
    {
        $this->stripeInvoiceId = $stripeInvoiceId;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPaidSubscription(): ?PaidSubscription
    {
        return $this->paidSubscription;
    }

    public function setPaidSubscription(?PaidSubscription $paidSubscription): self
    {
        $this->paidSubscription = $paidSubscription;

        return $this;
    }

    public function getPaidAt(): ?int
    {
        return $this->paidAt;
    }

    public function setPaidAt(int $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }
}
