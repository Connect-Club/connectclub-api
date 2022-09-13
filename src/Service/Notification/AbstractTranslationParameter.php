<?php

namespace App\Service\Notification;

use App\Entity\User\Device;

abstract class AbstractTranslationParameter implements SpecificTranslationParameterInterface
{
    private ?Device $device = null;

    public function forDevice(Device $device)
    {
        $this->device = $device;
    }

    protected function getDevice(): ?Device
    {
        return $this->device;
    }
}
