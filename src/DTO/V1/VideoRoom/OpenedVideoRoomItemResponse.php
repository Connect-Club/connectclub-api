<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\User;
use App\Entity\VideoChat\Location;
use Symfony\Component\Serializer\Annotation\Groups;

class OpenedVideoRoomItemResponse
{
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $name;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $description;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $password;

    /**
     * @var User
     * @Groups({"default"})
     */
    public User $author;

    /**
     * @var Location
     * @Groups({"default"})
     */
    public Location $location;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $width;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $height;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $resizerUrl;

    /**
     * @var boolean
     * @Groups({"default"})
     */
    public bool $open;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $schedule;

    public function __construct(
        bool $open,
        ?string $schedule,
        string $name,
        ?string $description,
        string $password,
        User $author,
        string $resizerUrl,
        Location $location,
        int $width,
        int $height
    ) {
        $this->open = $open;
        $this->schedule = $schedule;
        $this->name = $name;
        $this->description = $description;
        $this->password = $password;
        $this->author = $author;
        $this->resizerUrl = $resizerUrl;
        $this->location = $location;
        $this->width = $width;
        $this->height = $height;
    }
}
