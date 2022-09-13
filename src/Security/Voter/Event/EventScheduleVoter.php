<?php

namespace App\Security\Voter\Event;

use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class EventScheduleVoter extends Voter
{
    const EVENT_SCHEDULE_UPDATE = 'EVENT_SCHEDULE_UPDATE';
    const EVENT_SCHEDULE_START_EVENT = 'EVENT_SCHEDULE_START_EVENT';
    const EVENT_SCHEDULE_DELETE_EVENT = 'EVENT_SCHEDULE_DELETE_EVENT';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, [
            self::EVENT_SCHEDULE_UPDATE,
            self::EVENT_SCHEDULE_START_EVENT,
            self::EVENT_SCHEDULE_DELETE_EVENT,
        ]) && $subject instanceof EventSchedule;
    }

    /** @param EventSchedule $subject */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::EVENT_SCHEDULE_UPDATE:
            case self::EVENT_SCHEDULE_START_EVENT:
            case self::EVENT_SCHEDULE_DELETE_EVENT:
                return $subject->owner->equals($user) || !$subject
                            ->participants
                            ->filter(fn(EventScheduleParticipant $p) => $p->user->equals($user))
                            ->isEmpty();
        }

        return false;
    }
}
