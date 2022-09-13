<?php

namespace App\Service;

use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Repository\Location\CityRepository;
use App\Repository\Location\CountryRepository;
use InvalidArgumentException;
use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LocationManager
{
    private RequestStack $requestStack;
    private CountryRepository $countryRepository;
    private CityRepository $cityRepository;
    private Reader $reader;
    private LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        CityRepository $cityRepository,
        CountryRepository $countryRepository,
        Reader $reader,
        LoggerInterface $logger
    ) {
        $this->requestStack = $requestStack;
        $this->cityRepository = $cityRepository;
        $this->countryRepository = $countryRepository;
        $this->reader = $reader;
        $this->logger = $logger;
    }

    public function getCurrentCountry(): ?Country
    {
        if (!$ip = $this->getClientIp()) {
            return null;
        }

        if (!$locationData = $this->getLocationData($ip)) {
            return null;
        }

        $cityId = $locationData['country']['geoname_id'] ?? null;
        if (!$cityId) {
            $this->logger->warning('Cannot detect location info from ip '.$ip);

            return null;
        }

        return $this->countryRepository->find($cityId);
    }

    public function getCurrentCity(): ?City
    {
        if (!$ip = $this->getClientIp()) {
            return null;
        }

        if (!$locationData = $this->getLocationData($ip)) {
            return null;
        }

        $cityId = $locationData['city']['geoname_id'] ?? null;
        if (!$cityId) {
            $this->logger->warning('Cannot detect location info from ip '.$ip);

            return null;
        }

        return $this->cityRepository->find($cityId);
    }

    private function getLocationData(string $ip): ?array
    {
        try {
            $locationData = $this->reader->get($ip);
        } catch (Reader\InvalidDatabaseException $invalidDatabaseException) {
            $this->logger->error('MixMind error fetching ip info: '.$invalidDatabaseException->getMessage(), [
                'exception' => $invalidDatabaseException,
                'ip' => $ip,
            ]);

            return null;
        } catch (InvalidArgumentException $exception) {
            return null;
        }

        return $locationData;
    }

    private function getClientIp(): ?string
    {
        return $this->requestStack->getCurrentRequest()->getClientIp();
    }
}
