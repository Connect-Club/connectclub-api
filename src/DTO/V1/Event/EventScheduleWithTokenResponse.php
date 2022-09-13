<?php

namespace App\DTO\V1\Event;

use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V1\Ethereum\SlimTokenResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V2\User\LanguageDTO;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Interest\Interest;

class EventScheduleWithTokenResponse extends EventScheduleResponse
{
    const TOKEN_NOT_FOUND = 'token_not_found';
    const WALLET_NOT_REGISTERED = 'wallet_not_registered';
    const WALLET_ERROR = 'wallet_checking_error';

    /** @var bool */
    public bool $isOwnerToken = false;

    /** @var string|null */
    public ?string $tokenReason = null;

    /** @var SlimTokenResponse[] */
    public array $tokens = [];

    /** @var string|null */
    public ?string $tokenLandingUrlInformation = null;
}
