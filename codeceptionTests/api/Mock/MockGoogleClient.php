<?php

namespace App\Tests\Mock;

use \Google_Client;

class MockGoogleClient extends Google_Client
{
    public $sub;
    public $email;
    public $verified_email;
    public $given_name;
    public $family_name;
    public $picture;
    public $locale;

    public function verifyIdToken($idToken = null)
    {
        $result = [];
        $result['sub'] = $this->sub;
        $result['email'] = $this->email;
        $result['email_verified'] = $this->verified_email;
        $result['given_name'] = $this->given_name;
        $result['family_name'] = $this->family_name;
        $result['picture'] = $this->picture;
        $result['locale'] = $this->locale;

        return $result;
    }
}
