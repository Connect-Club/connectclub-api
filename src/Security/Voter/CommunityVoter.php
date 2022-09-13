<?php

namespace App\Security\Voter;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityParticipant;
use App\Entity\User;
use App\Repository\VideoChat\VideoRoomBanRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CommunityVoter extends Voter
{
    private VideoRoomBanRepository $videoRoomBanRepository;

    const COMMUNITY_JOIN = 'COMMUNITY_JOIN';
    const COMMUNITY_UPDATE = 'COMMUNITY_UPDATE';
    const COMMUNITY_LEAVE = 'COMMUNITY_LEAVE';
    const COMMUNITY_UPDATE_PHOTO = 'COMMUNITY_UPDATE_PHOTO';
    const COMMUNITY_BAN_USER = 'COMMUNITY_BAN_USER';

    public function __construct(VideoRoomBanRepository $videoRoomBanRepository)
    {
        $this->videoRoomBanRepository = $videoRoomBanRepository;
    }

    protected function supports($attribute, $subject)
    {
        return in_array($attribute, [
                self::COMMUNITY_JOIN,
                self::COMMUNITY_LEAVE,
                self::COMMUNITY_UPDATE,
                self::COMMUNITY_UPDATE_PHOTO,
                self::COMMUNITY_BAN_USER,
            ]) && $subject instanceof Community;
    }

    /**
     * @param string    $attribute
     * @param Community $subject
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $participant = $subject->getParticipant($user);

        switch ($attribute) {
            case self::COMMUNITY_JOIN:
                if ($this->videoRoomBanRepository->findBan($user, $subject->videoRoom)) {
                    return false;
                }
                return true;
            case self::COMMUNITY_UPDATE_PHOTO:
            case self::COMMUNITY_UPDATE:
            case self::COMMUNITY_BAN_USER:
                return $subject->owner->getId() === $user->getId() ||
                       ($participant && $participant->role == CommunityParticipant::ROLE_MODERATOR);
            case self::COMMUNITY_LEAVE:
                return $subject->owner->getId() !== $user->getId();
        }

        return false;
    }
}
