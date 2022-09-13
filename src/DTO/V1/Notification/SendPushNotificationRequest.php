<?php

namespace App\DTO\V1\Notification;

use Swagger\Annotations as SWG;

class SendPushNotificationRequest
{
    /**
     * @var string|null
     */
    public ?string $title = null;

    /**
     * @var string|null
     */
    public ?string $message = null;

    /**
     * @var int|null
     */
    public ?int $contentAvailable = null;

    /**
     * @SWG\Property(type="object")
     * @var array|null
     */
    public ?array $custom = null;
}
