<?php

namespace App\Service\SMS;

use App\Entity\User\SmsVerification;
use App\Repository\User\SmsVerificationRepository;
use App\Service\EventLogManager;
use App\Service\IpQualityScoreClient;
use App\Service\PhoneNumberManager;
use Exception;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Throwable;
use Traversable;
use function GuzzleHttp\Promise\iter_for;

class TwoFactorAuthenticatorManager
{
    /** @var SmsProviderInterface[] */
    private iterable $providers;
    private SmsVerificationRepository $smsVerificationRepository;
    private PhoneNumberManager $phoneNumberManager;
    private EventLogManager $eventLogManager;
    private Reader $reader;
    private IpQualityScoreClient $ipQualityScoreClient;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;

    private array $priority = [
        TestPhoneNumberSmsProvider::CODE,
        VonageSmsProvider::CODE,
        TwilioSmsProvider::CODE,
    ];

    public function __construct(
        Traversable $providers,
        SmsVerificationRepository $smsVerificationRepository,
        PhoneNumberManager $phoneNumberManager,
        EventLogManager $eventLogManager,
        LoggerInterface $logger,
        IpQualityScoreClient $ipQualityScoreClient,
        Reader $reader,
        LockFactory $lockFactory
    ) {
        $this->providers = $providers;
        $this->smsVerificationRepository = $smsVerificationRepository;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;
        $this->ipQualityScoreClient = $ipQualityScoreClient;
        $this->reader = $reader;
        $this->lockFactory = $lockFactory;
    }

    public function sendVerificationRequest(
        PhoneNumber $phoneNumber,
        ?string $ip = null,
        ?string $claim = null,
        ?Key $jwtClaimLockKey = null
    ) {
        $availableProviders = array_map(
            fn(SmsProviderInterface $p) => $p->getProviderCode(),
            array_filter(
                iterator_to_array($this->providers),
                fn(SmsProviderInterface $p) => $p->supportPhoneNumber($phoneNumber)
            )
        );

        $phoneNumberString = $this->phoneNumberManager->formatE164($phoneNumber);
        $phoneCountryIsoCode = PhoneNumberUtil::getInstance()->getRegionCodeForCountryCode(
            $this->phoneNumberManager->parse($phoneNumberString)->getCountryCode()
        );
        $lastSmsVerification = $this->smsVerificationRepository->findSmsVerification($phoneNumberString);

        $rating = $this->smsVerificationRepository->findRatingProvidersForPhoneNumber(
            $phoneNumberString,
            $phoneCountryIsoCode
        );
        $this->eventLogManager->logEventCustomObject(
            'rating_sms_providers',
            'phone_number',
            $phoneNumberString,
            $rating
        );

        $rating = array_values(
            array_filter(
                $rating,
                fn(array $item) => in_array($item['provider_code'], $availableProviders)
            )
        );

        $priorityProviderCode = $rating[0]['provider_code'] ?? null;
        if ($lastSmsVerification &&
            !$lastSmsVerification->cancelledAt &&
            $priorityProviderCode &&
            !in_array($priorityProviderCode, $availableProviders)) {
            $providerCode = $priorityProviderCode;
        } else {
            $lastProviderCode = $lastSmsVerification->providerCode ?? null;
            $providerCode = $this->roundRobinProviderCode($availableProviders, $lastProviderCode);
        }

        if (!$providerCode) {
            throw new RuntimeException('No provider code for number');
        }

        $smsVerification = new SmsVerification($phoneNumberString, '', $ip, $providerCode);
        $smsVerification->jwtClaim = $claim;

        try {
            $smsVerification->phoneCountryIsoCode = $phoneCountryIsoCode;

            if ($ip) {
                $locationData = $this->reader->get($ip);
                $smsVerification->ipCountryIsoCode = $locationData['country']['iso_code'] ?? null;
            }
        } catch (Throwable $exception) {
        }

        if ($ip) {
            $smsVerification->fraudScore = $this->ipQualityScoreClient->calculateFraudScore($smsVerification);
        }

        try {
            $smsVerification->remoteId = $this->getProvider($providerCode)->sendVerificationCode(
                $smsVerification,
                $phoneNumber
            );
        } catch (Exception $exception) {
            $smsVerification->cancelledAt = $smsVerification->createdAt;
            $this->logger->error($exception, ['exception' => $exception]);
        }
        $this->smsVerificationRepository->save($smsVerification);

        try {
            if ($jwtClaimLockKey) {
                $this->lockFactory->createLock($jwtClaimLockKey, 300)->release();
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception);
        }
    }

    public function checkVerificationCode(PhoneNumber $phoneNumber, string $code): bool
    {
        $lastSmsVerifications = $this->smsVerificationRepository->findLastSmsVerifications(
            $this->phoneNumberManager->formatE164($phoneNumber)
        );

        $lastSmsVerifications = array_filter($lastSmsVerifications, fn(SmsVerification $v) => $v->cancelledAt === null);
        if (!$lastSmsVerifications) {
            return false;
        }

        /** @var SmsVerification $smsVerification */
        foreach ($lastSmsVerifications as $smsVerification) {
            $provider = $this->getProvider($smsVerification->providerCode);
            if ($provider->checkVerificationCode($smsVerification, $code)) {
                $smsVerification->authorizedAt = time();
                $this->smsVerificationRepository->save($smsVerification);

                return true;
            }
        }

        return false;
    }

    private function getProvider(string $providerCode): SmsProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getProviderCode() === $providerCode) {
                return $provider;
            }
        }

        throw new RuntimeException(sprintf('Provider with code %s not found', $providerCode));
    }

    private function roundRobinProviderCode(array $availableCodeProviders, ?string $lastProvider): ?string
    {
        $availableCodeProviders = array_values($availableCodeProviders);

        if (!$lastProvider) {
            return array_shift($availableCodeProviders);
        }

        if (count($availableCodeProviders) === 1) {
            return array_pop($availableCodeProviders);
        }

        $keyLastProvider = array_search($lastProvider, $availableCodeProviders);

        return $availableCodeProviders[$keyLastProvider + 1] ?? $availableCodeProviders[0] ?? null;
    }
}
