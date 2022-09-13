<?php

namespace App\MessageHandler;

use App\Client\VonageSMSClient;
use App\Entity\User\SmsVerification;
use App\Message\SendSmsMessage;
use App\Repository\User\SmsVerificationRepository;
use App\Service\EventLogManager;
use App\Service\PhoneNumberManager;
use App\Service\SMS\TwoFactorAuthenticatorManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;
use Twilio\Rest\Client;
use Vonage\Client\Exception\Request as RequestException;
use Vonage\Verify\Request;

final class SendSmsMessageHandler implements MessageHandlerInterface
{
    private PhoneNumberManager $phoneNumberManager;
    private LockFactory $lockFactory;
    private TwoFactorAuthenticatorManager $twoFactorAuthenticatorManager;

    public function __construct(
        TwoFactorAuthenticatorManager $twoFactorAuthenticatorManager,
        PhoneNumberManager $phoneNumberManager,
        LockFactory $lockFactory
    ) {
        $this->twoFactorAuthenticatorManager = $twoFactorAuthenticatorManager;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(SendSmsMessage $message)
    {
        $lock = $this->lockFactory->createLock('rollback_sms_verification_'.md5($message->phoneNumber));
        if (!$lock->acquire()) {
            $lock->release();
            return;
        } else {
            $lock->release();
        }

        $this->twoFactorAuthenticatorManager->sendVerificationRequest(
            $this->phoneNumberManager->parse($message->phoneNumber),
            $message->ip,
            $message->claim,
            $message->jwtClaimLockKey
        );
    }
}
