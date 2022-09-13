<?php

namespace App\Command;

use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Entity\User;
use App\Repository\Location\CityRepository;
use App\Repository\Location\CountryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseMixMindDatabaseCommand extends Command
{
    protected static $defaultName = 'ParseMixMindDatabase';

    private CountryRepository $countryRepository;
    private CityRepository $cityRepository;
    private EntityManagerInterface $entityManager;
    private array $countriesCache = [];
    private float $lastMemoryUsage = 0;

    public function __construct(
        CountryRepository $countryRepository,
        CityRepository $cityRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->countryRepository = $countryRepository;
        $this->cityRepository = $cityRepository;
        $this->entityManager = $entityManager;

        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this
            ->setDescription('Download locations from MixMind csv files')
            ->addOption('country-dir', '', InputOption::VALUE_REQUIRED, 'Directory with csv countries files')
            ->addOption('city-dir', '', InputOption::VALUE_REQUIRED, 'Directory with csv cities files')
            ->addOption('check', '', InputOption::VALUE_NONE, 'Check difference')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2048M');

        //Fix memory leak
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        $io = new SymfonyStyle($input, $output);

        $dirCountry = rtrim($input->getOption('country-dir'), '/');
        $dirCity = rtrim($input->getOption('city-dir'), '/');

        if ($input->hasOption('check')) {
            $io->comment('Start check entities...');
            $this->processCheckDifference($io, $dirCity);
            $io->comment('Finish check entities');
        } else {
            if ($dirCountry) {
                $io->comment('Start parsing countries...');
                $countCountriesProcessed = $this->processParseCountries($io, $dirCountry);
                $io->comment('Finish parsing countries: '.$countCountriesProcessed.'.');
            }

            if ($dirCity) {
                $io->comment('Start parsing cities...');
                $countCountriesProcessed = $this->processParseCities($io, $dirCity);
                $io->comment('Finish parsing cities: '.$countCountriesProcessed.'.');
            }
        }

        $this->entityManager->flush();

        return 0;
    }

    private function processCheckDifference(SymfonyStyle $io, string $dirCity)
    {
        $cities = [];
        foreach ($this->parseFile($dirCity.'/GeoIP2-City-Locations-en.csv') as $city) {
            $cities[$city['geoname_id']] = $city;
        }

        $cityIdsMixmind = array_map(
            fn(array $city) => $city['geoname_id'],
            array_filter($cities, fn(array $city) => !empty($city['city_name']))
        );

        $cityIdsLocalDatabase = $this->cityRepository->createQueryBuilder('c')->select('c.id')->getQuery()->getResult();
        $cityIdsLocalDatabase = array_map(fn(array $row) => $row['id'], $cityIdsLocalDatabase);

        $incorrectDatabaseCity = array_diff($cityIdsLocalDatabase, $cityIdsMixmind);

        echo implode(',', $incorrectDatabaseCity);
    }

    private function processParseCities(SymfonyStyle $io, string $dir): int
    {
        $this->captureMemoryUsage($io, '');
        $translationRepository = $this->entityManager->getRepository(Translation::class);

        $this->warmCountriesCache();

        $countries = new ArrayCollection($this->countryRepository->findAll());

        $cities = [];
        foreach ($this->parseFile($dir.'/GeoIP2-City-Locations-en.csv') as $city) {
            $cities[$city['geoname_id']] = $city;
        }

        $cityLocales = [];
        foreach (glob($dir.'/GeoIP2-City-Locations-*.csv') as $countryLocaleFile) {
            $locale = str_replace([$dir.'/GeoIP2-City-Locations-', '.csv'], '', $countryLocaleFile);

            $localeData = explode('-', $locale);
            if (isset($localeData[1])) {
                $locale = $localeData[0].'_'.$localeData[1];
            } else {
                $locale = $locale.'_'.$locale;
            }
            $locale = strtolower($locale);

            $this->captureMemoryUsage($io, 'Loading locale '.$locale);

            $cityLocales[$locale] = [];
            foreach ($this->parseFile($countryLocaleFile) as $localeData) {
                $cityLocales[$locale][$localeData['geoname_id']] = $localeData;
            }
        }

        $tmpEn = $cityLocales['en_en'];
        unset($cityLocales['en_en']);
        $cityLocales['en_en'] = $tmpEn;

        $io->progressStart(count($cities));
        $processedCount = 0;
        foreach ($cities as $i => $city) {
            if (!$cityEntity = $this->cityRepository->find($city['geoname_id'])) {
                $cityEntity = new City();
            }

            $locale = $cityLocales['en_en'][$city['geoname_id']];

            $cityEntity->id = $city['geoname_id'];
            $cityEntity->name = $locale['city_name'];
            $cityEntity->subdivisionFirstIsoCode = $locale['subdivision_1_iso_code'];
            $cityEntity->subdivisionFirstName = $locale['subdivision_1_name'];
            $cityEntity->subdivisionSecondIsoCode = $locale['subdivision_2_iso_code'];
            $cityEntity->subdivisionSecondName = $locale['subdivision_2_name'];
            $cityEntity->metroCode = $locale['metro_code'];
            $cityEntity->latitude = isset($city['latitude']) ? (float) $city['latitude'] : 0;
            $cityEntity->longitude = isset($city['longitude']) ? (float) $city['longitude'] : 0;
            $cityEntity->accuracyRadius = isset($city['accuracy_radius']) ? (int) $city['accuracy_radius'] : 0;
            $cityEntity->timeZone = $locale['time_zone'];
            $cityEntity->locale = 'en_en';

            $country = $countries->matching(
                Criteria::create()
                    ->where(Criteria::expr()->eq('isoCode', $locale['country_iso_code']))
                    ->andWhere(Criteria::expr()->eq('name', $locale['country_name']))
            )->first();

            $cityEntity->country = $country;

            foreach ($cityLocales as $cityLocaleCode => $cityLocale) {
                if (empty($cityLocale[$cityEntity->id]['city_name'])) {
                    continue;
                }

                $translation = $translationRepository->findOneBy([
                    'locale' => $cityLocaleCode,
                    'objectClass' => City::class,
                    'field' => 'name',
                    'foreignKey' => $cityEntity->id,
                ]);

                if (!$translation) {
                    $translation = new Translation();
                }

                $translation->setField('name');
                $translation->setForeignKey($cityEntity->id);
                $translation->setObjectClass(City::class);
                $translation->setLocale($cityLocaleCode);
                $translation->setContent($cityLocale[$cityEntity->id]['city_name']);

                $this->entityManager->persist($translation);
            }

            $this->entityManager->persist($cityEntity);
            ++$processedCount;

            if (0 === ($processedCount % 100)) {
                $this->entityManager->flush();
                //Fix memory leak
                $this->entityManager->clear();
                gc_collect_cycles();
                $countries = new ArrayCollection($this->countryRepository->findAll());
            }

            $io->progressAdvance();
        }
        $io->progressFinish();

        $this->entityManager->flush();

        return $processedCount;
    }

    private function processParseCountries(SymfonyStyle $io, string $dir): int
    {
        $this->captureMemoryUsage($io, '');

        $countries = [];
        foreach ($this->parseFile($dir.'/GeoIP2-Country-Locations-en.csv') as $country) {
            $countries[$country['geoname_id']] = $country;
        }

        $io->comment('Start loading locales');
        $countriesLocales = [];
        foreach (glob($dir.'/GeoIP2-Country-Locations-*.csv') as $countryLocaleFile) {
            $locale = str_replace([$dir.'/GeoIP2-Country-Locations-', '.csv'], '', $countryLocaleFile);

            $localeData = explode('-', $locale);
            if (isset($localeData[1])) {
                $locale = $localeData[0].'_'.$localeData[1];
            } else {
                $locale = $locale.'_'.$locale;
            }
            $locale = strtolower($locale);

            $this->captureMemoryUsage($io, 'Loading locale '.$locale);

            $countriesLocales[$locale] = [];
            foreach ($this->parseFile($countryLocaleFile) as $localeData) {
                $countriesLocales[$locale][$localeData['geoname_id']] = $localeData;
            }
        }
        if (isset($countriesLocales['en_en'])) {
            $tmp = $countriesLocales['en_en'];
            unset($countriesLocales['en_en']);
            $countriesLocales['en_en'] = $tmp;
        }
        $io->comment('Finish loading locales');

        $translationRepository = $this->entityManager->getRepository(Translation::class);

        $io->progressStart(count($countries));
        $processedCount = 0;
        foreach ($countries as $i => $country) {
            $locale = $countriesLocales['en_en'][$country['geoname_id']];
            $countryId = (int) $country['geoname_id'];
            if (!$countryEntity = $this->countryRepository->find($countryId)) {
                $countryEntity = new Country();
            }

            $countryEntity->id = $countryId;
            $countryEntity->name = $locale['country_name'];
            $countryEntity->continentCode = $locale['continent_code'];
            $countryEntity->continentName = $locale['continent_name'];
            $countryEntity->isInEuropeanUnion = '1' == $locale['is_in_european_union'];
            $countryEntity->isoCode = $locale['country_iso_code'];

            $this->entityManager->persist($countryEntity);

            foreach ($countriesLocales as $countriesLocaleCode => $countriesLocale) {
                $translation = $translationRepository->findOneBy([
                    'locale' => $countriesLocaleCode,
                    'objectClass' => Country::class,
                    'field' => 'name',
                    'foreignKey' => $countryEntity->id,
                ]);

                if (!$translation) {
                    $translation = new Translation();
                }

                $translation->setField('name');
                $translation->setForeignKey((string) $countryEntity->id);
                $translation->setObjectClass(Country::class);
                $translation->setLocale($countriesLocaleCode);
                $translation->setContent($countriesLocale[$countryEntity->id]['country_name']);

                $this->entityManager->persist($translation);
            }

            if (0 === $processedCount % 100) {
                $this->entityManager->flush();
            }

            $io->progressAdvance();
            ++$processedCount;
        }
        $io->progressFinish();

        $this->entityManager->flush();

        return $processedCount;
    }

    private function parseFile(string $scvFile): \Generator
    {
        $fp = fopen($scvFile, 'r');
        $title = fgetcsv($fp);

        while ($singleLine = fgetcsv($fp)) {
            yield array_combine($title, $singleLine);
        }
    }

    private function warmCountriesCache()
    {
        $countriesCache = $this->countryRepository->findAll();

        foreach ($countriesCache as $i => $country) {
            $this->countriesCache[$country->id] = $country;
        }
    }

    private function captureMemoryUsage(SymfonyStyle $io, string $comment)
    {
        if (!$this->lastMemoryUsage) {
            $this->lastMemoryUsage = memory_get_usage(true);
            $io->comment('Memory usage start: '.$this->lastMemoryUsage);
        } else {
            $diff = (memory_get_usage(true) - $this->lastMemoryUsage) / (1024 * 1024);
            $totalUsage = $this->lastMemoryUsage = memory_get_usage(true);
            $totalUsage /= 1024 * 1024;
            if ($diff < 0) {
                $io->comment('Memory free ['.$comment.'] '.$diff.' mb. Total usage: '.$totalUsage.' mb.');
            } else {
                $io->comment('Memory usage ['.$comment.'] '.$diff.' mb. Total usage: '.$totalUsage.' mb.');
            }
        }
    }
}
