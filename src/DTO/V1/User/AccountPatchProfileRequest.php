<?php

namespace App\DTO\V1\User;

use App\DTO\V1\Interests\InterestDTO;
use App\Validator\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

class AccountPatchProfileRequest
{
    /**
     * @var string|null
     * @Assert\NotBlank(allowNull=false, message="should_not_be_blank")
     * @Assert\Length(max=120)
     */
    public ?string $name = null;

    /**
     * @var string|null
     * @Assert\AtLeastOneOf(
     *     @Assert\Length(max=120, maxMessage="max_value_reached"),
     *     @Assert\Blank()
     * )
     */
    public ?string $surname = null;

    /**
     * @var string|null
     * @Assert\AtLeastOneOf(
     *     @Assert\Length(max=120, maxMessage="max_value_reached"),
     *     @Assert\Blank()
     * )
     */
    public ?string $company = null;

    /**
     * @var string|null
     * @Assert\AtLeastOneOf(
     *     @Assert\Length(max=120, maxMessage="max_value_reached"),
     *     @Assert\Blank()
     * )
     */
    public ?string $position = null;

    /**
     * @var string|null
     * @Assert\AtLeastOneOf(
     *     @Assert\Length(max=240, maxMessage="max_value_reached"),
     *     @Assert\Blank()
     * )
     */
    public ?string $about = null;

    /**
     * @var string|null
     * @PhoneNumber(type="mobile", message="not_valid_mobile_phone_number")
     */
    public ?string $phone = null;

    /**
     * @var int|null
     */
    public ?int $avatar = null;

    /**
     * @var AccountPatchProfileLocation|null
     */
    public ?AccountPatchProfileLocation $city = null;

    /**
     * @var AccountPatchProfileLocation|null
     */
    public ?AccountPatchProfileLocation $country = null;

    /**
     * @var InterestDTO[]
     */
    public array $interests = [];
}
