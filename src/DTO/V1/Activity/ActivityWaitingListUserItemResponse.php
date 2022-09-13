<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\ActivityWithPhoneNumberInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class ActivityWaitingListUserItemResponse extends ActivityItemResponse
{
    /** @var string */
    public string $phone;

    public function __construct(ActivityWithPhoneNumberInterface $activity, string $title)
    {
        $this->phone = PhoneNumberUtil::getInstance()->format($activity->getPhoneNumber(), PhoneNumberFormat::E164);

        parent::__construct($activity, $title);
    }
}
