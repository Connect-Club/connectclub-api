<?php

namespace App\Service\SMS;

use App\Entity\User\SmsVerification;
use libphonenumber\PhoneNumber;

interface SmsProviderInterface
{
    public function getProviderCode(): string;
    public function supportPhoneNumber(PhoneNumber $phoneNumber): bool;
    public function sendVerificationCode(SmsVerification $smsVerification, PhoneNumber $phoneNumber): string;
    public function checkVerificationCode(SmsVerification $smsVerification, string $code): bool;
}
