<?php

namespace App\Tests\Mock;

use Facebook\GraphNodes\GraphUser;

class MockGraphUser extends GraphUser
{
    public $id;
    public $email;
    public $name;
    public $picture;
    public $isSilhouette;

    public function __construct($data)
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->name = $data['name'];
        $this->picture = $data['picture'];
        $this->isSilhouette = $data['isSilhouette'];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getPicture()
    {
        $result = [];
        $result['isSilhouette'] = $this->isSilhouette;
        $result['picture'] = $this->picture;
        return new MockGraphPicture($result);
    }
}
