<?php

namespace App\Entity\User;

use App\Repository\User\SmsVerificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=SmsVerificationRepository::class)
 */
class SmsVerification
{
    const VONAGE_PROVIDER_CODE = 'vonage';
    const TWILIO_PROVIDER_CODE = 'twilio';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string") */
    public string $phoneNumber;

    /** @ORM\Column(type="string") */
    public string $remoteId;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $ip = null;

    /** @ORM\Column(type="string", options={"default": SmsVerification::VONAGE_PROVIDER_CODE}) */
    public string $providerCode;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $cancelledAt = null;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $authorizedAt = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $ipCountryIsoCode = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $phoneCountryIsoCode = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $code = null;

    /** @ORM\Column(type="float", nullable=true) */
    public ?float $fraudScore = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $jwtClaim = null;

    /** @ORM\Column(type="integer") */
    public int $createdAt;

    public function __construct(
        string $phoneNumber,
        string $vonageRequestId,
        ?string $ip = null,
        string $providerCode = self::VONAGE_PROVIDER_CODE
    ) {
        $this->id = Uuid::uuid4();
        $this->phoneNumber = $phoneNumber;
        $this->remoteId = $vonageRequestId;
        $this->ip = $ip;
        $this->providerCode = $providerCode;
        $this->createdAt = time();
    }

    public function cancel()
    {
        $this->cancelledAt = time();
    }
}
