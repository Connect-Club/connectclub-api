<?php

namespace App\DTO\V1\VideoRoom;

use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectSquareDataResponse
{
    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $name;

    public function __construct(?string $name)
    {
        $this->name = $name;
    }
}
