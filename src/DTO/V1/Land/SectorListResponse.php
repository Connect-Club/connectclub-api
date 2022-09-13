<?php

namespace App\DTO\V1\Land;

class SectorListResponse
{
    /** @var int */
    public int $sector;

    /** @var LandResponse[] */
    public array $parcels = [];
}
