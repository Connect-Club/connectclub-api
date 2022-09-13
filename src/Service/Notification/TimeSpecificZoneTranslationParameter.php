<?php

namespace App\Service\Notification;

use App\Util\TimeZone;
use RuntimeException;

class TimeSpecificZoneTranslationParameter extends AbstractTranslationParameter
{
    private string $format;
    private int $timeUTC;

    public function __construct(int $timeUTC, string $format = 'l, F d \a\t h:i A')
    {
        $this->format = $format;
        $this->timeUTC = $timeUTC;
    }

    public function __toString()
    {
        $device = $this->getDevice();

        if (!$device) {
            throw new RuntimeException('Device not set');
        }

        return date(
            $this->format,
            TimeZone::getTimestampWithUserTimeZone($this->timeUTC, $device->user, $device)
        );
    }
}
