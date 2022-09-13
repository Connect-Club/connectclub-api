<?php

namespace App\Service\Notification\Push;

use App\Entity\User\Device;
use App\Service\Notification\Message;

interface PushNotification
{
    const PARAMETER_TYPE = 'type';
    const PARAMETER_SPECIFIC_KEY = 'specific_key';
    const PARAMETER_INITIATOR_ID = 'initiator_id';
    const PARAMETER_IMAGE = 'large-icon';
    const PARAMETER_SECOND_IMAGE = 'large-image';

    public function supportDevice(Device $device): bool;
    public function getMessage(): Message;
    public function getPredefinedTranslationParameters(): array;
}
