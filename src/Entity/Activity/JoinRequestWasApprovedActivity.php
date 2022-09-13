<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Repository\Activity\JoinRequestWasApprovedActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=JoinRequestWasApprovedActivityRepository::class)
 */
class JoinRequestWasApprovedActivity extends Activity implements ClubActivityInterface
{
    /** @ORM\ManyToOne(targetEntity=Club::class) */
    public Club $club;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $clubRole = null;

    public function __construct(Club $joinRequest, string $clubRole, User $user, User ...$users)
    {
        parent::__construct($user, ...$users);

        $this->club = $joinRequest;
        switch ($clubRole) {
            case ClubParticipant::ROLE_OWNER:
                $clubRole = 'creator';
                break;
            case ClubParticipant::ROLE_MODERATOR:
                $clubRole = 'administrator';
                break;
        }
        $this->clubRole = $clubRole;
    }

    public function getType(): string
    {
        return Activity::TYPE_JOIN_REQUEST_WAS_APPROVED;
    }

    public function getClub(): Club
    {
        return $this->club;
    }
}
