<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoomHistory;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VideoRoomHistoryVoter extends Voter
{
    const HISTORY_DELETE = 'HISTORY_DELETE';

    protected function supports($attribute, $subject)
    {
        return in_array($attribute, [self::HISTORY_DELETE])
            && $subject instanceof VideoRoomHistory;
    }

    /**
     * @param string           $attribute
     * @param VideoRoomHistory $subject
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        switch ($attribute) {
            case self::HISTORY_DELETE:
                return $user->getId() == $subject->user->id;
        }

        return false;
    }
}
