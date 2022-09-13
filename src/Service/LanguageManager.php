<?php

namespace App\Service;

use App\Entity\User\Language;
use App\Repository\User\LanguageRepository;
use MaxMind\Db\Reader;

class LanguageManager
{
    private Reader $reader;
    private LanguageRepository $languageRepository;

    public function __construct(Reader $reader, LanguageRepository $languageRepository)
    {
        $this->reader = $reader;
        $this->languageRepository = $languageRepository;
    }

    public function findLanguageByIp(?string $ip): ?Language
    {
        $detectedRegionCode = null;

        if ($ip) {
            try {
                $locationData = $this->reader->get($ip);
                $countryIsoCode = $locationData['country']['iso_code'] ?? null;
                if ($countryIsoCode) {
                    $detectedRegionCode = $countryIsoCode;
                }
            } catch (Reader\InvalidDatabaseException $invalidDatabaseException) {
            }
        }

        $languages = [];
        if ($detectedRegionCode) {
            $languages = $this->languageRepository->findAutofillInterestsForRegionCode($detectedRegionCode);
        }

        if (!$languages) {
            $languages = $this->languageRepository->findBy(['isDefaultInterestForRegions' => true]);
        }

        return $languages[0] ?? null;
    }
}
