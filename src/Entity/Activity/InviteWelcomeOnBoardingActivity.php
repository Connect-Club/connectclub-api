<?php

namespace App\Entity\Activity;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Activity\InviteWelcomeOnBoardingActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InviteWelcomeOnBoardingActivityRepository::class)
 */
class InviteWelcomeOnBoardingActivity extends Activity implements ActivityWithVideoRoomInterface
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    public VideoRoom $videoRoom;

    public function __construct(VideoRoom $videoRoom, User $user, User ...$users)
    {
        parent::__construct($user, ...$users);

        $this->videoRoom = $videoRoom;
    }

    public function getType(): string
    {
        return self::TYPE_INVITE_ON_BOARDING;
    }

    public function getVideoRoom(): VideoRoom
    {
        return $this->videoRoom;
    }
}
