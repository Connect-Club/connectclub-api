<?php

namespace App\Tests\Mock;

use Facebook\GraphNodes\GraphPicture;

class MockGraphPicture extends GraphPicture
{
    public $picture;
    public $isSilhouette;

    public function __construct($data)
    {
        $this->picture = $data['picture'];
        $this->isSilhouette = $data['isSilhouette'];
    }

    public function isSilhouette()
    {
        return $this->isSilhouette;
    }

    public function getUrl()
    {
        return $this->picture;
    }
}
