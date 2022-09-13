<?php

namespace App\Tests;

use Codeception\Util\HttpCode;

class SwaggerCest
{
    public function swaggerTest(ApiTester $I)
    {
        $I->sendGet('/doc.json');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
