<?php

namespace App\Service\SMS;

use App\Entity\User\SmsVerification;
use App\Service\PhoneNumberManager;
use libphonenumber\PhoneNumber;

class TestPhoneNumberSmsProvider implements SmsProviderInterface
{
    const CODE = 'test';

    private PhoneNumberManager $phoneNumberManager;

    public function __construct(PhoneNumberManager $phoneNumberManager)
    {
        $this->phoneNumberManager = $phoneNumberManager;
    }

    public function getProviderCode(): string
    {
        return self::CODE;
    }

    public function supportPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        $phoneNumberString = $this->phoneNumberManager->formatE164($phoneNumber);

        return $phoneNumberString == '+18006927753' ||
               $this->phoneNumberManager->isTestPhone($phoneNumberString) ||
               $_ENV['STAGE'] == 1;
    }

    public function sendVerificationCode(SmsVerification $smsVerification, PhoneNumber $phoneNumber): string
    {
        return '';
    }

    public function checkVerificationCode(SmsVerification $smsVerification, string $code): bool
    {
        return $code === '1111';
    }
}
