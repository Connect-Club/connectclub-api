<?php

namespace App\DTO\V1\VideoRoom;

class VideoRoomMeetingStatisticsResponse
{
    public int $id;
    public ?string $name;
    public ?string $surname;
    public int $duration;

    /**
     * VideoRoomMeetingStatisticsResponse constructor.
     *
     * @param string $name
     * @param string $surname
     */
    public function __construct(int $id, ?string $name, ?string $surname, int $duration)
    {
        $this->id = $id;
        $this->name = $name;
        $this->surname = $surname;
        $this->duration = $duration;
    }
}
