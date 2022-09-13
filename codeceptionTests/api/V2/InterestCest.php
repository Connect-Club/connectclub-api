<?php

namespace App\Tests\V2;

use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class InterestCest extends BaseCest
{
    public function testListInterests(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v2/interests');

        $I->assertEquals(
            '🎬 Arts',
            $I->grabDataFromResponseByJsonPath('$.response[0].name')[0]
        );
        $I->assertEquals(
            '🎨 Design',
            $I->grabDataFromResponseByJsonPath('$.response[0].interests[0][0].name')[0]
        );
        $I->assertEquals(
            '🍔 Food and Drink',
            $I->grabDataFromResponseByJsonPath('$.response[0].interests[0][1].name')[0]
        );
        $I->assertEquals(
            '🔥 Burning Man',
            $I->grabDataFromResponseByJsonPath('$.response[0].interests[1][0].name')[0]
        );
        $I->assertEquals(
            '📷 Photography',
            $I->grabDataFromResponseByJsonPath('$.response[0].interests[1][1].name')[0]
        );
        $I->assertEquals(
            '📖 Writing',
            $I->grabDataFromResponseByJsonPath('$.response[0].interests[1][2].name')[0]
        );

        $I->seeResponseContainsJson([
            'response' => [
                [
                    'name' => '🎬 Arts',
                    'interests' => [
                        [
                            ['name' => '🎨 Design'],
                            ['name' => '🍔 Food and Drink'],
                        ],
                        [
                            ['name' => '🔥 Burning Man'],
                            ['name' => '📷 Photography'],
                            ['name' => '📖 Writing'],
                        ],
                    ],
                ],
                [
                    'name' => '🔥 Hustle',
                    'interests' => [
                        [
                            ['name' => '🎯 Pitch Practice'],
                            ['name' => '🌱 Networking'],
                            ['name' => '🎵 TikTok'],
                        ],
                        [
                            ['name' => '🏠 Real Estate'],
                            ['name' => '🌈 Instagram'],
                            ['name' => '📷 Photography'],
                        ],
                    ],
                ],
                [
                    'name' => '💬 Languages',
                    'interests' => [
                    ],
                ]
            ]
        ]);


        $I->sendGet('/v2/interests?withLanguages=false');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson([
            'name' => '💬 Languages',
            'interests' => [
                [
                    ['name' => '🇬🇧 English'],
                    ['name' => '🇷🇺 Russian'],
                    ['name' => '🇩🇪 German'],
                ],
            ],
        ]);
    }
}
