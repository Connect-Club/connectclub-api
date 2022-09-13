<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\User;
use App\Repository\Activity\NewClubInviteActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NewClubInviteActivityRepository::class)
 */
class NewClubInviteActivity extends Activity implements ClubActivityInterface
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Club\Club")
     */
    private Club $club;

    public function __construct(Club $club, User $user, ...$users)
    {
        $this->club = $club;

        parent::__construct($user, ...$users);
    }

    public function getClub(): Club
    {
        return $this->club;
    }

    public function getType(): string
    {
        return self::TYPE_NEW_CLUB_INVITE;
    }
}
