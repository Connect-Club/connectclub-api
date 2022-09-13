<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\Location\LocationSuggestionsCityResponse;
use App\DTO\V1\Location\LocationSuggestionsCountryResponse;
use App\DTO\V1\Location\PhoneNumberCountryItemResponse;
use App\DTO\V1\Location\PhoneNumberCountryResponse;
use App\Entity\Location\Country;
use App\Entity\Statistic\Installation;
use App\Repository\Location\CityRepository;
use App\Repository\Location\CountryRepository;
use App\Repository\Statistic\InstallationRepository;
use App\Swagger\ListResponse;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberToCarrierMapper;
use libphonenumber\PhoneNumberUtil;
use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\DTO\V1\Location\CountryResponse as CountryDTO;
use Throwable;

/**
 * Class LocationController.
 *
 * @Route("/location")
 */
class LocationController extends BaseController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @SWG\Get(
     *     description="Suggestions for cities",
     *     summary="Suggestions for cities",
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Parameter(in="query", name="q", description="Query", type="string"),
     *     tags={"Location"}
     * )
     * @ListResponse(entityClass=LocationSuggestionsCityResponse::class)
     * @Route("/suggestions/{countryId}", methods={"GET"})
     */
    public function suggestions(Request $request, CityRepository $cityRepository, string $countryId)
    {
        $query = $request->query->get('q');

        if (!$query) {
            return $this->handleResponse([]);
        }

        $cities = $cityRepository->findSuggestions($query, $countryId);

        $suggestions = [];
        foreach ($cities as $city) {
            $suggestions[] = new LocationSuggestionsCityResponse(
                $city->id,
                $city->name
            );
        }

        return $this->handleResponse($suggestions);
    }

    /**
     * @SWG\Get(
     *     description="List all countries",
     *     summary="List all countries",
     *     @SWG\Response(response="200", description="Success response"),
     *     tags={"Location"}
     * )
     * @ListResponse(entityClass=CountryDTO::class, groups={"v1.location.countries", "default"})
     * @Route("/countries", methods={"GET"})
     */
    public function countries(CountryRepository $countryRepository)
    {
        $countries = array_map(fn(Country $country) => new CountryDTO($country), $countryRepository->findAll());

        return $this->handleResponse($countries, Response::HTTP_OK, ['v1.location.countries']);
    }

    /**
     * @SWG\Get(
     *     description="List all countries code phone",
     *     summary="List all countries code phone",
     *     @SWG\Response(response="200", description="Success response"),
     *     tags={"Location"}
     * )
     * @ListResponse(entityClass=PhoneNumberCountryResponse::class)
     * @Route("/phone-number-formats", methods={"GET"})
     */
    public function countryNumberFormats(
        Request $request,
        Reader $reader,
        CountryRepository $countryRepository,
        InstallationRepository $installationRepository
    ): JsonResponse {
        $util = PhoneNumberUtil::getInstance();

        $countries = [];
        foreach ($countryRepository->createQueryBuilder('c')->getQuery()->getArrayResult() as $country) {
            $countries[$country['isoCode']] = $country;
        }

        $result = [];
        foreach ($util->getSupportedRegions() as $region) {
            $regionMetadata = $util->getMetadataForRegion($region);
            $regionPrefix = $util->getCountryCodeForRegion($region);

            $mobilePhoneNumberExample = $util->getExampleNumberForType($region, PhoneNumberType::MOBILE);
            if (!$mobilePhoneNumberExample) {
                continue;
            }

            $exampleInternationalFormat = $util->format($mobilePhoneNumberExample, PhoneNumberFormat::INTERNATIONAL);
            $format = ltrim(str_replace('+'.$regionPrefix, '', $exampleInternationalFormat));
            $formatPattern = preg_replace('/[0-9]/', '#', $format);

            $result[$region] = new PhoneNumberCountryItemResponse(
                (string) $regionPrefix,
                '(?:' . $regionMetadata->getGeneralDesc()->getNationalNumberPattern() . ')',
                $regionMetadata->getMobile()->getPossibleLength(),
                $exampleInternationalFormat,
                $formatPattern,
                $countries[$region]['name'] ?? ''
            );
        }

        $ip = $request->getClientIp();
        $detectedRegionCode = 'RU';

        if ($ip) {
            try {
                $locationData = $reader->get($ip);
                $countryIsoCode = $locationData['country']['iso_code'] ?? null;
                if ($countryIsoCode && isset($result[$countryIsoCode])) {
                    $detectedRegionCode = $countryIsoCode;
                }
            } catch (Reader\InvalidDatabaseException $invalidDatabaseException) {
                $this->logger->error('MixMind error fetching ip info: '.$invalidDatabaseException->getMessage(), [
                    'exception' => $invalidDatabaseException,
                    'ip' => $ip,
                ]);
            }
        }

        return $this->handleResponse(new PhoneNumberCountryResponse($detectedRegionCode, $result));
    }
}
