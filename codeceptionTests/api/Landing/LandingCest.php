<?php

namespace App\Tests\Landing;

use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;

class LandingCest extends BaseCest
{
    public function acceptance(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/landing', json_encode([
            'name' => 'Landing',
            'status' => 'hide',
            'url' => 'success-valid-url',
            'title' => 'Title',
            'subtitle' => 'SubTitle',
            'params' => [
                'seo' => [
                    'title' => 'Seo title',
                    'facebookCursor' => 'cursor',
                ],
                'moduleSpeakers' => [
                    'speakers' => [
                        [
                            'name' => 'John'
                        ]
                    ]
                ],
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'params' => [
                    'seo' => [
                        'title' => 'Seo title',
                        'facebookCursor' => 'cursor',
                    ],
                    'moduleSpeakers' => [
                        'speakers' => [
                            0 => [
                                'name' => 'John',
                            ],
                        ],
                    ],
                ],
                'name' => 'Landing',
                'status' => 'hide',
                'url' => 'success-valid-url',
                'title' => 'Title',
                'subtitle' => 'SubTitle',
            ],
        ]);

        $landingId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];

        $I->sendPatch('/v1/landing/'.$landingId, json_encode([
            'name' => 'Landing 2',
            'status' => 'active',
            'url' => 'success-valid-url-updated',
            'title' => 'Title updated',
            'subtitle' => 'SubTitle updated',
            'params' => [
                'seo' => [
                    'title' => 'Seo title updated',
                ],
                'moduleSpeakers' => null,
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'params' => [
                    'seo' => [
                        'title' => 'Seo title updated',
                    ],
                ],
                'id' => $landingId,
                'name' => 'Landing 2',
                'status' => 'active',
                'url' => 'success-valid-url-updated',
                'title' => 'Title updated',
                'subtitle' => 'SubTitle updated',
            ]
        ]);
    }
}
