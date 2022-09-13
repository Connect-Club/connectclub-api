<?php

namespace App\Entity\Activity;

use App\Entity\Club\JoinRequest;

interface JoinRequestActivityInterface extends ActivityInterface
{
    public function getJoinRequest(): JoinRequest;
}
