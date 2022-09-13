<?php

namespace App\Entity\Activity;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Activity\WelcomeOnBoardingFriendActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=WelcomeOnBoardingFriendActivityRepository::class)
 */
class WelcomeOnBoardingFriendActivity extends Activity implements ActivityWithVideoRoomInterface
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
        return self::TYPE_WELCOME_ON_BOARDING_FRIEND;
    }

    public function getVideoRoom(): VideoRoom
    {
        return $this->videoRoom;
    }
}
