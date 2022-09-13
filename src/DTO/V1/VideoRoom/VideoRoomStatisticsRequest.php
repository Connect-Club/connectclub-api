<?php

namespace App\DTO\V1\VideoRoom;

use stdClass;
use Swagger\Annotations as SWG;

class VideoRoomStatisticsRequest
{
    /** @var string */
    public string $roomname;

    /**
     * @var array[]
     * @SWG\Property(
     *     type="array",
     *     @SWG\Items(
     *         type="array",
     *         @SWG\Items(type="string")
     *     )
     * )
     */
    public array $stat;
}
