<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;

interface ClubActivityInterface extends ActivityInterface
{
    public function getClub(): Club;
}
