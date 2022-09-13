<?php

namespace App\Entity\Activity;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Activity\StartedVideoRoomActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StartedVideoRoomActivityRepository::class)
 */
class StartedVideoRoomActivity extends Activity implements ActivityWithVideoRoomInterface
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    private VideoRoom $videoRoom;

    public function __construct(VideoRoom $videoRoom, User $user, User ...$users)
    {
        parent::__construct($user, ...$users);

        $this->videoRoom = $videoRoom;
    }

    public function getType(): string
    {
        return self::TYPE_VIDEO_ROOM_STARTED;
    }

    public function getVideoRoom(): VideoRoom
    {
        return $this->videoRoom;
    }
}
