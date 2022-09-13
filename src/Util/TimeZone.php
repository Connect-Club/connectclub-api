<?php

namespace App\Util;

use App\Entity\User;
use DateTime;
use DateTimeZone;

class TimeZone
{
    public static function getTimestampWithUserTimeZone(
        int $dateTimeInUTC,
        User $forUser,
        ?User\Device $device = null
    ): int {
        if ($device) {
            $timeZoneDifferenceWithUTC = $device->getTimeZoneDifferenceWithUTCInMinutes() * 60;
        } else {
            $timeZoneDifferenceWithUTC = $forUser->getTimeZoneDifferenceWithUTC();
        }

        $time = $dateTimeInUTC - $timeZoneDifferenceWithUTC;
        if (!$timeZoneDifferenceWithUTC && $forUser->city) {
            $timeZone = $forUser->city->timeZone;

            $dateTimeUserTimeZone = new DateTime('now', new DateTimeZone($timeZone));
            $offset = $dateTimeUserTimeZone->getOffset();

            if ($offset != false) {
                $time = $time + $offset;
            }
        }

        return $time;
    }
}
