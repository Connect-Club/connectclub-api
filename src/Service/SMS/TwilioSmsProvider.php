<?php

namespace App\Service\SMS;

use App\Entity\User\SmsVerification;
use App\Repository\User\SmsVerificationRepository;
use App\Service\EventLogManager;
use App\Service\PhoneNumberManager;
use App\Service\TwilioEndpointManager;
use libphonenumber\PhoneNumber;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;

class TwilioSmsProvider implements SmsProviderInterface
{
    const CODE = 'twilio';

    private SmsVerificationRepository $smsVerificationRepository;
    private TwilioEndpointManager $twilioEndpointManager;
    private EventLogManager $eventLogManager;
    private PhoneNumberManager $phoneNumberManager;
    private LoggerInterface $logger;

    public function __construct(
        SmsVerificationRepository $smsVerificationRepository,
        TwilioEndpointManager $twilioEndpointManager,
        EventLogManager $eventLogManager,
        PhoneNumberManager $phoneNumberManager,
        LoggerInterface $logger
    ) {
        $this->smsVerificationRepository = $smsVerificationRepository;
        $this->twilioEndpointManager = $twilioEndpointManager;
        $this->eventLogManager = $eventLogManager;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->logger = $logger;
    }

    public function getProviderCode(): string
    {
        return self::CODE;
    }

    public function supportPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        return $_ENV['STAGE'] != 1 && !$this->phoneNumberManager->isTestPhone(
            $this->phoneNumberManager->formatE164($phoneNumber)
        );
    }

    public function sendVerificationCode(SmsVerification $smsVerification, PhoneNumber $phoneNumber): string
    {
        $phoneNumberString = $this->phoneNumberManager->formatE164($phoneNumber);
        $smsVerifications = $this->smsVerificationRepository->findLastSmsVerificationsForProvider(
            $phoneNumberString,
            $this->getProviderCode(),
        );

        //Cancel last active sms verifications
        foreach ($smsVerifications as $smsVerification) {
            if ($smsVerification->cancelledAt !== null) {
                $smsVerification->cancel();

                try {
                    if ((time() - $smsVerification->createdAt) > 600) {
                        $this->twilioEndpointManager->cancelVerificationCode($smsVerification);
                    }

                    $this->smsVerificationRepository->save($smsVerification);
                } catch (TwilioException $twilioException) {
                    $this->logger->error($twilioException, ['exception' => $twilioException]);
                }
            }
        }

        return $this->twilioEndpointManager->sendVerificationCode($phoneNumberString)->sid;
    }

    public function checkVerificationCode(SmsVerification $smsVerification, string $code): bool
    {
        $phoneNumberString = $smsVerification->phoneNumber;

        try {
            $verification = $this->twilioEndpointManager->checkVerificationCode($phoneNumberString, $code);
            $verify = $verification->status == 'approved';
            $context = $verification->toArray();
        } catch (TwilioException $exception) {
            $verify = false;
            $context = ['exceptionMessage' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()];
        }

        $this->eventLogManager->logEventCustomObject(
            'twilio.check_verification_request',
            'phone_number',
            $phoneNumberString,
            $context
        );

        return $verify;
    }
}
