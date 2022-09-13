<?php

namespace App\Tests\Mock;

use Anboo\AppleSign\ASDecoder;
use Anboo\AppleSign\Payload;

class MockASDecoder extends ASDecoder
{
    public $sub;
    public $email;
    public $emailVerified;

    public function decodeIdentityToken(string $identityToken) : Payload
    {
        $result = new Payload();
        $result->sub = $this->sub;
        $result->email = $this->email;
        $result->emailVerified = $this->emailVerified;

        return $result;
    }
}
