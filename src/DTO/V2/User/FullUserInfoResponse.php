<?php

namespace App\DTO\V2\User;

use App\DTO\V1\Club\ClubMemberOfResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Interest\Interest;
use App\Entity\Role;
use App\Entity\User;

class FullUserInfoResponse extends UserInfoWithFollowingData
{
    /** @var bool */
    public bool $isFollowing;

    /** @var bool */
    public bool $isFollows;

    /** @var UserInfoResponse|null */
    public ?UserInfoResponse $joinedBy;

    /** @var InterestDTO[] */
    public array $interests;

    /** @var int */
    public int $followers;

    /** @var int */
    public int $following;

    public bool $isSuperCreator;

    public ?InvitedToResponse $invitedTo = null;

    /**
     * @var ClubMemberOfResponse[]
     */
    public array $memberOf = [];

    public function __construct(
        User $user,
        bool $isFollowing,
        bool $isFollows,
        int $followers,
        int $following
    ) {
        parent::__construct($user, $isFollowing, $isFollows);

        $this->joinedBy = $user->invite ? new UserInfoResponse($user->invite->author) : null;
        $this->interests = $user->interests->map(fn(Interest $i) => new InterestDTO($i))->getValues();
        $this->followers = $followers;
        $this->following = $following;

        if ($user->invite && $user->invite->club) {
            $this->invitedTo = new InvitedToResponse($user->invite);
        }

        foreach ($user->clubParticipants as $participant) {
            $this->memberOf[] = new ClubMemberOfResponse($participant->club, $participant->role);
        }

        uasort($this->memberOf, function (ClubMemberOfResponse $a, ClubMemberOfResponse $b) {
            if ($a->clubRole == $b->clubRole) {
                return 0;
            }

            $roles = [
                ClubParticipant::ROLE_MEMBER => 1,
                ClubParticipant::ROLE_MODERATOR => 2,
                ClubParticipant::ROLE_OWNER => 3,
            ];

            if ($roles[$a->clubRole] < $roles[$b->clubRole]) {
                return 1;
            } else {
                return -1;
            }
        });
        $this->memberOf = array_values($this->memberOf);

        $this->isSuperCreator = $user->hasRole(Role::ROLE_SUPERCREATOR);
    }
}
