<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Activity\NewJoinRequestActivityRepository;

/**
 * @ORM\Entity(repositoryClass=NewJoinRequestActivityRepository::class)
 */
class NewJoinRequestActivity extends Activity implements JoinRequestActivityInterface, ClubActivityInterface
{
    /** @ORM\ManyToOne(targetEntity=JoinRequest::class) */
    private JoinRequest $joinRequest;

    public function __construct(JoinRequest $joinRequest, User $user, User ...$users)
    {
        $this->joinRequest = $joinRequest;

        parent::__construct($user, ...$users);
    }

    public function getClub(): Club
    {
        return $this->joinRequest->club;
    }

    public function getType(): string
    {
        return self::TYPE_NEW_JOIN_REQUEST;
    }

    public function getJoinRequest(): JoinRequest
    {
        return $this->joinRequest;
    }
}
