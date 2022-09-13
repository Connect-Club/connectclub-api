<?php

namespace App\DTO\V1\Event;

use App\DTO\V2\User\UserInfoResponse;
use App\Entity\User;

class EventScheduleParticipantResponse extends UserInfoResponse
{
    /** @var bool */
    public bool $isOwner;

    /** @var bool */
    public bool $isSpecialGuest;

    /** @var string|null */
    public ?string $role;

    /** @var string|null */
    public ?string $clubRole = null;

    public function __construct(User $user, bool $isOwner, bool $isSpecialGuest = false, ?string $roleClub = null)
    {
        parent::__construct($user);

        $this->isOwner = $isOwner;
        $this->isSpecialGuest = $isSpecialGuest;
        $this->clubRole = $roleClub;
    }
}
