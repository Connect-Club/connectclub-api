<?php

namespace App\Service;

use App\DTO\V1\User\PhoneContactNumberResponse;
use App\Entity\User;
use App\Repository\User\PhoneContactNumberRepository;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Contracts\Cache\CacheInterface;

class PhoneNumberManager
{
    /** @var PhoneNumber[] */
    private static array $inMemoryCache = [];

    private PhoneContactNumberRepository $phoneContactNumberRepository;
    private PhoneNumberUtil $phoneNumberUtil;
    private CacheInterface $cache;
    private string $testPhonePrefix;

    public function __construct(
        PhoneContactNumberRepository $phoneContactNumberRepository,
        CacheInterface $cache,
        string $testPhonePrefix
    ) {
        $this->phoneContactNumberRepository = $phoneContactNumberRepository;
        $this->cache = $cache;
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
        $this->testPhonePrefix = $testPhonePrefix;
    }

    public function parse(string $phoneNumber, $region = PhoneNumberUtil::UNKNOWN_REGION): ?PhoneNumber
    {
        if (isset(PhoneNumberManager::$inMemoryCache[$phoneNumber])) {
            return PhoneNumberManager::$inMemoryCache[$phoneNumber];
        }

        return PhoneNumberManager::$inMemoryCache[md5($phoneNumber)] = $this->cache->get(
            'phone_number_'.md5($phoneNumber),
            fn() => $this->phoneNumberUtil->parse($phoneNumber, $region)
        );
    }

    public function formatE164(PhoneNumber $phoneNumber): string
    {
        $cacheKey = 'format_'.md5(serialize($phoneNumber));

        if (isset(PhoneNumberManager::$inMemoryCache[$cacheKey])) {
            return PhoneNumberManager::$inMemoryCache[$cacheKey];
        }

        return PhoneNumberManager::$inMemoryCache[$cacheKey] = $this->cache->get(
            'phone_number_'.$cacheKey,
            fn() => $this->phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164)
        );
    }

    public function findPhoneNumbersDataForContacts(array $phoneContactIds): array
    {
        $phoneNumbersData = array_map(
            'array_values',
            $this->phoneContactNumberRepository->findAllPhoneNumbersDataForUser($phoneContactIds)
        );

        $additionalPhoneNumbers = [];
        foreach ($phoneNumbersData as list($phoneNumber, $phoneContactsId, $isInvited, $isPending)) {
            if ($isInvited) {
                $status = 'invited';
            } else {
                $status = $isPending ? 'pending' : 'new';
            }

            $additionalPhoneNumbers[$phoneContactsId] ??= [];
            $additionalPhoneNumbers[$phoneContactsId][] = [$phoneNumber, $status];
        }

        return $additionalPhoneNumbers;
    }

    public function findPhoneNumbersDataForNumbers(User $user, array $phoneContactNumbers): array
    {
        $phoneNumbersData = array_map(
            'array_values',
            $this->phoneContactNumberRepository->findAllPhoneNumbersData($user, $phoneContactNumbers)
        );

        $additionalPhoneNumbers = [];
        foreach ($phoneNumbersData as list($phoneNumber, $isInvited, $isPending)) {
            if ($isInvited) {
                $status = 'invited';
            } else {
                $status = $isPending ? 'pending' : 'new';
            }

            $additionalPhoneNumbers[$phoneNumber] ??= [];
            $additionalPhoneNumbers[$phoneNumber][] = [$phoneNumber, $status];
        }

        return $additionalPhoneNumbers;
    }

    public function getPhoneNumberUtil(): PhoneNumberUtil
    {
        return $this->phoneNumberUtil;
    }

    public function isTestPhone(string $phone): bool
    {
        if (!$this->testPhonePrefix) {
            return false;
        }

        return mb_strpos($phone, $this->testPhonePrefix) !== false;
    }
}
