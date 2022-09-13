<?php

namespace App\Security\Voter;

use App\Entity\Community\CommunityParticipant;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class VideoRoomVoter extends Voter
{
    const VIDEO_ROOM_CHANGE_CONFIGURATION = 'VIDEO_ROOM_CHANGE_CONFIGURATION';
    const VIDEO_ROOM_CHANGE_CUSTOM_CONFIGURATION = 'VIDEO_ROOM_CHANGE_CUSTOM_CONFIGURATION';
    const VIDEO_ROOM_UPLOAD_BACKGROUND = 'VIDEO_ROOM_UPLOAD_BACKGROUND';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, [
                self::VIDEO_ROOM_CHANGE_CUSTOM_CONFIGURATION,
                self::VIDEO_ROOM_CHANGE_CONFIGURATION,
                self::VIDEO_ROOM_UPLOAD_BACKGROUND,
            ]) && $subject instanceof VideoRoom;
    }

    /**
     * @param string    $attribute
     * @param VideoRoom $subject
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();

        switch ($attribute) {
            case self::VIDEO_ROOM_CHANGE_CUSTOM_CONFIGURATION:
                return $this->security->isGranted('ROLE_UNITY_SERVER');
            case self::VIDEO_ROOM_CHANGE_CONFIGURATION:
            case self::VIDEO_ROOM_UPLOAD_BACKGROUND:
            default:
                $communityParticipant = $subject->community->getParticipant($user);
                $communityParticipantRole = $communityParticipant ? $communityParticipant->role : null;

                return $this->security->isGranted('ROLE_ADMIN') ||
                       in_array(
                           $communityParticipantRole,
                           [CommunityParticipant::ROLE_MODERATOR, CommunityParticipant::ROLE_ADMIN]
                       );
        }
    }
}
