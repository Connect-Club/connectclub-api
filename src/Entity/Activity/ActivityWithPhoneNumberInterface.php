<?php

namespace App\Entity\Activity;

use libphonenumber\PhoneNumber;

interface ActivityWithPhoneNumberInterface extends ActivityInterface
{
    public function getPhoneNumber(): PhoneNumber;
}
