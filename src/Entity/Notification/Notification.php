<?php

namespace App\Entity\Notification;

use App\Entity\User;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\Push\PriorityPushNotification;
use App\Service\Notification\Push\PushNotification;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=NotificationRepository::class)
 */
class Notification
{
    const STATUS_IGNORED = 'ignored';
    const STATUS_ERROR = 'error';
    const STATUS_ERROR_PROCESSING = 'error_processing';
    const STATUS_PROCESS = 'process';
    const STATUS_PROCESSED = 'processed';
    const STATUS_SEND = 'send';
    const STATUS_OPENED = 'opened';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string") */
    public string $type;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $specificKey = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $message;

    /** @ORM\Column(type="json") */
    public array $messageParameters = [];

    /** @ORM\Column(type="integer") */
    public int $initiatorId;

    /** @ORM\Column(type="integer") */
    public int $recipientId;

    /** @ORM\Column(type="string") */
    public string $recipientDeviceToken;

    /** @ORM\Column(type="integer") */
    public int $priority = 0;

    /** @ORM\Column(type="string") */
    public string $status = self::STATUS_PROCESSED;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $reason = null;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $startProcessAt = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $processedAt = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $sendAt = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $errorAt = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $openedAt = null;

    public function __construct(
        User\Device $recipient,
        PushNotification $notification
    ) {
        $message = $notification->getMessage();

        $this->id = Uuid::uuid4();
        $this->type = (string) $message->getMessageParameter(PushNotification::PARAMETER_TYPE);
        $this->specificKey = $message->getMessageParameter(PushNotification::PARAMETER_SPECIFIC_KEY);
        $this->message = $message->getMessage();
        $this->messageParameters = $message->getMessageParameters();
        $this->initiatorId = (int) $message->getMessageParameter(PushNotification::PARAMETER_INITIATOR_ID);
        $this->recipientDeviceToken = (string) $recipient->token;
        $this->recipientId = (int) $recipient->user->getId();
        $this->createdAt = (int) (microtime(true) * 1000);
    }

    public function ignore(?string $reason = null): self
    {
        $this->status = self::STATUS_IGNORED;
        $this->reason = $reason;

        return $this;
    }

    public function startProcess()
    {
        $this->status = self::STATUS_PROCESSED;
        $this->startProcessAt = (int) (microtime(true) * 1000);
    }

    public function doneProcess()
    {
        $this->status = self::STATUS_PROCESSED;
        $this->processedAt = (int) (microtime(true) * 1000);
    }

    public function markAsSend()
    {
        $this->status = self::STATUS_SEND;
        $this->sendAt = (int) (microtime(true) * 1000);
    }

    public function markAsOpened()
    {
        $this->status = self::STATUS_OPENED;
        $this->openedAt = (int) (microtime(true) * 1000);
    }

    public function getAmplitudeData(): array
    {
        return [
            'id' => $this->id->toString(),
            'type' => $this->type,
            'specific_key' => $this->specificKey,
            'initiator_id' => $this->initiatorId,
            'recipient_id' => $this->recipientId,
            'title' => $this->messageParameters['title'] ?? '',
            'message' => $this->message ?? '',
        ];
    }
}
