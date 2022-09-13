<?php

namespace App\Tests\Location;

use App\DataFixtures\AccessTokenFixture;
use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LocationCest extends BaseCest
{
    public function listCountries(ApiTester $I)
    {
        $I->loadFixtures(new class extends AbstractFixture implements DependentFixtureInterface {
            public function getDependencies()
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $country = new Country();
                $country->id = 2017370;
                $country->isoCode = 'RU';
                $country->name = 'Russia';
                $country->continentCode = 'EU';
                $country->continentName = 'Europe';
                $country->isInEuropeanUnion = true;
                $manager->persist($country);

                $country = new Country();
                $country->id = 2077456;
                $country->isoCode = 'OC';
                $country->name = 'Oceania';
                $country->continentCode = 'AU';
                $country->continentName = 'Australia';
                $country->isInEuropeanUnion = false;
                $manager->persist($country);

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGET('/v1/location/countries');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict([
            [
                'id' => 'integer',
                'name' => 'string',
            ],
            [
                'id' => 'integer',
                'name' => 'string',
            ],
            [
                'id' => 'integer',
                'name' => 'string',
            ]
        ]);
        $I->seeResponseContainsJson([
            [
                'id' => 2017370,
                'name' => 'Russia'
            ],
            [
                'id' => 2077456,
                'name' => 'Oceania'
            ]
        ]);
    }

    public function citySuggestions(ApiTester $I)
    {
        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $country = new Country();
                $country->id = 2017370;
                $country->isoCode = 'RU';
                $country->name = 'Russia';
                $country->continentCode = 'EU';
                $country->continentName = 'Europe';
                $country->isInEuropeanUnion = true;
                $manager->persist($country);

                $city = new City();
                $city->id = 524901;
                $city->name = 'Moscow';
                $city->subdivisionFirstIsoCode = 'MOW';
                $city->subdivisionFirstName = 'Moscow';
                $city->subdivisionSecondIsoCode = '';
                $city->subdivisionSecondName = '';
                $city->metroCode = '';
                $city->latitude = 0;
                $city->longitude = 0;
                $city->accuracyRadius = 0;
                $city->timeZone = 'Europe/Moscow';
                $city->country = $country;
                $manager->persist($city);

                $country = new Country();
                $country->id = 2077456;
                $country->isoCode = 'OC';
                $country->name = 'Oceania';
                $country->continentCode = 'AU';
                $country->continentName = 'Australia';
                $country->isInEuropeanUnion = false;
                $manager->persist($country);

                $city = new City();
                $city->id = 2164837;
                $city->name = 'Moscow';
                $city->subdivisionFirstIsoCode = 'MOW';
                $city->subdivisionFirstName = 'Moscow';
                $city->subdivisionSecondIsoCode = '';
                $city->subdivisionSecondName = '';
                $city->metroCode = '';
                $city->latitude = 0;
                $city->longitude = 0;
                $city->accuracyRadius = 0;
                $city->timeZone = 'Europe/Moscow';
                $city->country = $country;
                $manager->persist($city);

                $city = new City();
                $city->id = 1672422;
                $city->name = '';
                $city->subdivisionFirstIsoCode = 'MOW';
                $city->subdivisionFirstName = 'Moscow';
                $city->subdivisionSecondIsoCode = '';
                $city->subdivisionSecondName = '';
                $city->metroCode = '';
                $city->latitude = 0;
                $city->longitude = 0;
                $city->accuracyRadius = 0;
                $city->timeZone = 'Europe/Moscow';
                $city->country = $country;
                $manager->persist($city);

                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGET('/v1/location/suggestions/2017370?q=mos');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict([
            [
                'id' => 'integer',
                'name' => 'string',
            ]
        ]);
        $I->seeResponseContainsJson([
            [
                'id' => 524901,
                'name' => 'Moscow',
            ]
        ]);
        $cities = $I->grabDataFromResponseByJsonPath('$.response')[0];
        $I->assertCount(1, $cities);

        $I->sendGET('/v1/location/suggestions/2077456?q=mos');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict([
            [
                'id' => 'integer',
                'name' => 'string',
            ]
        ]);
        $I->seeResponseContainsJson([
            [
                'id' => 2164837,
                'name' => 'Moscow',
            ]
        ]);
        $cities = $I->grabDataFromResponseByJsonPath('$.response')[0];
        $I->assertCount(1, $cities);

        $I->sendGET('/v1/location/suggestions/2077456?q=');
        $cities = $I->grabDataFromResponseByJsonPath('$.response')[0];
        $I->assertCount(0, $cities);
    }
}
