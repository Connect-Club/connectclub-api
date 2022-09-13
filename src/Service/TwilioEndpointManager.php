<?php

namespace App\Service;

use App\Entity\User\SmsVerification;
use Twilio\Rest\Client;
use Twilio\Rest\Verify\V2\Service\VerificationCheckInstance;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;

class TwilioEndpointManager
{
    private Client $twilioClient;

    public function __construct(Client $twilioClient)
    {
        $this->twilioClient = $twilioClient;
    }

    public function checkVerificationCode(string $phoneNumber, string $code): VerificationCheckInstance
    {
        $sid = $_ENV['TWILIO_VERIFY_SERVICE_SID'];

        return $this->twilioClient->verify->v2->services($sid)->verificationChecks->create(
            $code,
            ['to' => $phoneNumber]
        );
    }

    public function cancelVerificationCode(SmsVerification $verification)
    {
        $this->twilioClient
             ->verify
             ->v2
             ->services($_ENV['TWILIO_VERIFY_SERVICE_SID'])
             ->verifications($verification->remoteId)
             ->update('cancelled');
    }

    public function sendVerificationCode(string $phoneNumber): VerificationInstance
    {
        return $this->twilioClient->verify->services(
            $_ENV['TWILIO_VERIFY_SERVICE_SID']
        )->verifications->create($phoneNumber, 'sms', ['locale' => 'en']);
    }
}
