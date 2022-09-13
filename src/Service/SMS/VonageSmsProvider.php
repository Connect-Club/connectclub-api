<?php

namespace App\Service\SMS;

use App\Client\VonageSMSClient;
use App\Entity\User\SmsVerification;
use App\Repository\User\SmsVerificationRepository;
use App\Service\EventLogManager;
use App\Service\PhoneNumberManager;
use libphonenumber\PhoneNumber;
use Psr\Log\LoggerInterface;
use Throwable;
use Vonage\Client\Exception\Request as RequestException;
use Vonage\Verify\Request;

class VonageSmsProvider implements SmsProviderInterface
{
    const CODE = 'vonage';

    private SmsVerificationRepository $smsVerificationRepository;
    private PhoneNumberManager $phoneNumberManager;
    private VonageSMSClient $client;
    private EventLogManager $eventLogManager;
    private LoggerInterface $logger;

    public function __construct(
        SmsVerificationRepository $smsVerificationRepository,
        PhoneNumberManager $phoneNumberManager,
        VonageSMSClient $client,
        EventLogManager $eventLogManager,
        LoggerInterface $logger
    ) {
        $this->smsVerificationRepository = $smsVerificationRepository;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->client = $client;
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;
    }

    public function getProviderCode(): string
    {
        return self::CODE;
    }

    public function supportPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        if ($_ENV['STAGE'] == 1) {
            return false;
        }

        $phoneNumberString = $this->phoneNumberManager->formatE164($phoneNumber);
        if ($this->phoneNumberManager->isTestPhone($phoneNumberString)) {
            return false;
        }

        $util = $this->phoneNumberManager->getPhoneNumberUtil();
        $countryCode = $util->getRegionCodeForNumber($phoneNumber);

        $blackListVonage = [
            'AF','AG','AI','AL','AM','AO','AW','BB','BF','BI',
            'BQ','BZ','CD','CF','CG','CI','CM','DJ','DM','DZ',
            'GA','GD','GH','GM','GN','GQ','GW','GY','HT','IQ',
            'JM','KM','KN','KY','LC','LR','MC','ME','MG','MK',
            'ML','MR','MS','MV','MW','MZ','NE','PG','PS','PW',
            'SB','SC','SL','SN','SO','SS','ST','SV','SX','TC',
            'TD','TG','TJ','TL','TN','TO','VC','VG','VU','WS',
            'ZM','ZW'
        ];

        return !in_array($countryCode, $blackListVonage);
    }

    public function sendVerificationCode(SmsVerification $smsVerification, PhoneNumber $phoneNumber): string
    {
        $phoneNumberString = $this->phoneNumberManager->formatE164($phoneNumber);

        $smsVerifications = $this->smsVerificationRepository->findLastSmsVerificationsForProvider(
            $phoneNumberString,
            $this->getProviderCode(),
        );

        foreach ($smsVerifications as $verification) {
            if ($verification->cancelledAt !== null) {
                $verification->cancel();

                try {
                    if ((time() - $verification->createdAt) > 300) {
                        $response = $this->client->verify()->cancel($verification->remoteId);

                        $this->eventLogManager->logEventCustomObject(
                            'success_cancel_sms_verification',
                            'phone_number',
                            $phoneNumberString,
                            $response->toArray()
                        );
                    }

                    $this->smsVerificationRepository->save($verification);
                } catch (Throwable $exception) {
                    $this->logger->error($exception, ['exception' => $exception]);

                    $this->eventLogManager->logEventCustomObject(
                        'error_cancel_sms_verification',
                        'phone_number',
                        $phoneNumberString,
                        $exception->getTrace()
                    );
                }
            }
        }

        $request = new Request($phoneNumberString, $_ENV['VONAGE_BRAND_NAME']);
        $request->setLocale('en-us');

        $util = $this->phoneNumberManager->getPhoneNumberUtil();
        $countryCode = $util->getRegionCodeForNumber($phoneNumber);

        if ($countryCode == 'RU') {
            $request->setSenderId($_ENV['VONAGE_SENDER_ID']);
        }

        return (string) $this->client->start($request)->getRequestId();
    }

    public function checkVerificationCode(SmsVerification $smsVerification, string $code): bool
    {
        try {
            $verification = $this->client->verify()->check($smsVerification->remoteId, $code);
            $context = $verification->toArray();

            $this->eventLogManager->logEventCustomObject(
                'vonage.sms_verification_correct_code',
                'phone_number',
                $smsVerification->phoneNumber,
                $context
            );

            return true;
        } catch (Throwable $requestException) {
            $context = [
                'exceptionMessage' => $requestException->getMessage(),
                'trace' => $requestException->getTraceAsString(),
            ];

            $this->eventLogManager->logEventCustomObject(
                'vonage.sms_verification_incorrect_code',
                'phone_number',
                $smsVerification->phoneNumber,
                $context
            );
        }

        return false;
    }
}
