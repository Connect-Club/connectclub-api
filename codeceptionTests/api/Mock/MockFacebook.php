<?php

namespace App\Tests\Mock;

use Facebook\Facebook;

class MockFacebook extends Facebook
{
    public $id;
    public $email;
    public $name;
    public $picture;
    public $isSilhouette;

    public function __construct()
    {
    }

    public function get($endpoint, $accessToken = null, $eTag = null, $graphVersion = null)
    {
        return $this;
    }

    public function getGraphUser()
    {
        $result = [];
        $result['id'] = $this->id;
        $result['email'] = $this->email;
        $result['name'] = $this->name;
        $result['picture'] = $this->picture;
        $result['isSilhouette'] = $this->isSilhouette;

        return new MockGraphUser($result);
    }
}
