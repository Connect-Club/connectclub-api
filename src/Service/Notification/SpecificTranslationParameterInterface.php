<?php

namespace App\Service\Notification;

use App\Entity\User\Device;
use Stringable;

interface SpecificTranslationParameterInterface extends Stringable
{
    public function forDevice(Device $device);
}
