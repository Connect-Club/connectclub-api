<?php

namespace App\DTO\V1\Event;

use App\Controller\ErrorCode;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V2\User\UserInfoResponse;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CreateEventScheduleRequest
{
    /**
     * @var string
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     */
    public $title;

    /**
     * @var UserInfoResponse[]
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     */
    public $participants;

    /**
     * @var UserInfoResponse[]
     */
    public $specialGuests;

    /**
     * @var int
     * @Assert\NotBlank(allowNull=false, message="cannot_be_empty")
     */
    public $date;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var InterestDTO[]
     */
    public $interests = [];

    /** @var string|null */
    public $festivalCode = null;

    /** @var string|null */
    public $festivalSceneId = null;

    /** @var int|null */
    public $dateEnd = null;

    /** @var int|null */
    public $language = null;

    /** @var bool */
    public bool $isPrivate = false;

    /** @var bool|null */
    public $forMembersOnly = null;

    public ?string $clubId = null;

    /** @var string[] */
    public ?array $tokenIds = null;

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        if ($this->date < time()) {
            $context
                ->buildViolation(ErrorCode::V1_EVENT_SCHEDULE_DATE_TIME_IS_NEGATIVE)
                ->atPath('date')
                ->addViolation();
        }

        if ($this->dateEnd !== null && $this->dateEnd <= $this->date) {
            $context
                ->buildViolation(ErrorCode::V1_EVENT_SCHEDULE_DATE_TIME_END_IS_NEGATIVE)
                ->atPath('dateEnd')
                ->addViolation();
        }
    }
}
