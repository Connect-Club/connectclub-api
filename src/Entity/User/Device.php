<?php

namespace App\Entity\User;

use App\Entity\Log\LoggableEntityInterface;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\User\DeviceRepository")
 */
class Device implements LoggableEntityInterface
{
    const TYPE_ANDROID = 'android';
    const TYPE_IOS = 'ios';
    const TYPE_ANDROID_REACT = 'android-react';
    const TYPE_IOS_REACT = 'ios-react';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="string", unique=true)
     */
    public string $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="devices") */
    public User $user;

    /** @ORM\Column(type="string") */
    public string $type;

    /** @ORM\Column(type="string", unique=true, nullable=true) */
    public ?string $token;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $model;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $timeZone;

    /** @ORM\Column(type="string") */
    public string $locale;

    /** @ORM\Column(type="integer") */
    public int $createdAt;

    public function __construct(
        string $id,
        User $user,
        string $type,
        ?string $token,
        ?string $timeZone,
        string $locale,
        ?string $model = null
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->type = $type;
        $this->token = $token;
        $this->timeZone = $timeZone;
        $this->locale = $locale;
        $this->model = $model;
        $this->createdAt = time();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTimeZoneDifferenceWithUTCInMinutes(): int
    {
        return (int) $this->timeZone;
    }

    public function getEntityCode(): string
    {
        return 'device';
    }
}
