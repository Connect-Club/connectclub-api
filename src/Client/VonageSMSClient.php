<?php

namespace App\Client;

use Vonage\Client\Credentials\Container;
use Vonage\Client\Credentials\Basic;
use Vonage\Client;
use Vonage\Verify\Request;
use Vonage\Verify\Verification;

class VonageSMSClient extends Client
{
    public function __construct()
    {
        parent::__construct(
            new Container(
                new Basic($_ENV['VONAGE_API_KEY'], $_ENV['VONAGE_API_SECRET'])
            )
        );
    }

    public function start(Request $request): Verification
    {
        return $this->verify()->start($request);
    }
}
