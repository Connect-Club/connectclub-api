<?php

namespace App\Security\Voter;

use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\User;
use App\Repository\Club\ClubParticipantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class ClubVoter extends Voter
{
    public const ASSIGN_MODERATOR = 'assign_moderator';
    public const REVOKE_MODERATOR = 'revoke_moderator';


    private Security $security;
    private ClubParticipantRepository $clubParticipantRepository;

    public function __construct(
        Security $security,
        ClubParticipantRepository $clubParticipantRepository
    ) {
        $this->security = $security;
        $this->clubParticipantRepository = $clubParticipantRepository;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::ASSIGN_MODERATOR, self::REVOKE_MODERATOR]) && $subject instanceof Club;
    }

    /**
     * @param string $attribute
     * @param Club   $subject
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($subject->owner->equals($user) || $this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $clubParticipant = $this->clubParticipantRepository->findOneBy([
            'club' => $subject,
            'user' => $user,
        ]);

        return null !== $clubParticipant && $clubParticipant->role === ClubParticipant::ROLE_MODERATOR;
    }
}
