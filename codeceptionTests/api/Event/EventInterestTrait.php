<?php

namespace App\Tests\Event;

use App\Tests\ApiTester;

trait EventInterestTrait
{
    private function assertInterests(ApiTester $I, $expectedInterestsByRoom): void
    {
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];

        $actualInterestsByRoom = [];
        foreach ($items as $item) {
            $actualInterestsByRoom[$item['title']] = [];
            foreach ($item['interests'] as $interest) {
                $actualInterestsByRoom[$item['title']][] = $interest['name'];
            }
        }

        $I->assertEquals($expectedInterestsByRoom, $actualInterestsByRoom);
    }
}
