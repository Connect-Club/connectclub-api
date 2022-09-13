<?php

namespace App\DTO\V2\User;

use App\Controller\ErrorCode;
use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\Reference\ReferenceResponse;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AccountPatchProfileRequest
{
    /**
     * @var string
     * @Assert\Regex(pattern="/^[a-zA-Z_.\-\d]+$/", message="incorrect_value")
     * @Assert\NotBlank(allowNull=true, message="cannot_be_empty")
     */
    public $username = null;

    /**
     * @var string
     * @Assert\NotBlank(allowNull=true, message="cannot_be_empty")
     */
    public $name = null;

    /**
     * @var string
     * @Assert\NotBlank(allowNull=true, message="cannot_be_empty")
     */
    public $surname = null;

    /**
     * @var integer
     * @Assert\NotBlank(allowNull=true, message="cannot_be_empty")
     */
    public $avatar = null;

    /**
     * @var string
     */
    public $about = null;

    /**
     * @var InterestDTO[]|null
     */
    public $interests = null;

    /**
     * @var ReferenceResponse[]|null
     */
    public $skills = null;

    /**
     * @var ReferenceResponse[]|null
     */
    public $industries = null;

    /**
     * @var ReferenceResponse[]|null
     */
    public $goals = null;

    /**
     * @var int|null
     */
    public $skipNotificationUntil = null;

    /**
     * @var int|null
     */
    public $languageId = null;

    /**
     * @var LanguageDTO[]|null
     */
    public $languages = null;

    /**
     * @var string|null
     */
    public $bio = null;

    /**
     * @var string|null
     * @Assert\Length(allowEmptyString=true, max=255, maxMessage="max_length_255")
     */
    public ?string $twitter = null;

    /**
     * @var string|null
     * @Assert\Length(allowEmptyString=true, max=255, maxMessage="max_length_255")
     */
    public ?string $linkedin = null;

    /**
     * @var string|null
     * @Assert\Length(allowEmptyString=true, max=255, maxMessage="max_length_255")
     */
    public ?string $instagram = null;

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        if ($this->skipNotificationUntil !== null &&
            $this->skipNotificationUntil !== 0 &&
            $this->skipNotificationUntil < time()) {
            $context
                ->buildViolation(ErrorCode::V1_ACCOUNT_SKIP_NOTIFICATIONS_INCORRECT_TIME)
                ->atPath('skipNotificationUntil')
                ->addViolation();
        }
    }
}
