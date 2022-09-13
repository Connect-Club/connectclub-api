<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Activity\StartedClubVideoRoomActivityRepository;

/**
 * @ORM\Entity(repositoryClass=StartedClubVideoRoomActivityRepository::class)
 */
class StartedClubVideoRoomActivity extends Activity implements ClubActivityInterface, ActivityWithVideoRoomInterface
{
    /** @ORM\ManyToOne(targetEntity=Club::class) */
    private Club $club;

    /** @ORM\ManyToOne(targetEntity=VideoRoom::class) */
    private VideoRoom $videoRoom;

    public function __construct(Club $joinRequest, VideoRoom $videoRoom, User $user, User ...$users)
    {
        parent::__construct($user, ...$users);

        $this->club = $joinRequest;
        $this->videoRoom = $videoRoom;
    }

    public function getType(): string
    {
        return Activity::TYPE_CLUB_VIDEO_ROOM_STARTED;
    }

    public function getVideoRoom(): VideoRoom
    {
        return $this->videoRoom;
    }

    public function getClub(): Club
    {
        return $this->club;
    }
}
