<?php

namespace App\DTO\V1\User;

use App\Entity\OAuth\AccessToken;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\User\AppleProfileData;
use App\Entity\User\FacebookProfileData;
use App\Entity\User\GoogleProfileData;
use Doctrine\Common\Collections\Criteria;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class FullUserInfoResponse extends UserInfoResponse
{
    /**
     * @var int|null
     */
    public ?int $id;

    /**
     * @var string|null
     */
    public ?string $username;

    /**
     * @var string|null
     */
    public ?string $email;

    /**
     * @var string|null
     */
    public ?string $company;

    /**
     * @var string|null
     */
    public ?string $position;

    /**
     * @var string|null
     */
    public ?string $about;

    /**
     * @var string|null
     */
    public ?string $phone;

    /**
     * @var string[]
     */
    public array $roles;

    /**
     * @var int
     */
    public int $createdAt;

    /**
     * @var UserInfoResponse|null
     */
    public ?UserInfoResponse $referer = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $deletedAt = null;

    public ?LastAccessTokenResponse $lastAccessToken;

    /** @var bool */
    public bool $banned;

    /** @var UserInfoResponse|null  */
    public ?UserInfoResponse $bannedBy = null;

    /** @var UserInfoResponse|null  */
    public ?UserInfoResponse $deletedBy = null;

    /** @var string|null */
    public ?string $deleteComment = null;

    /** @var string|null */
    public ?string $banComment = null;

    public function __construct(User $user)
    {
        parent::__construct($user);

        $this->id = $user->id;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->about = $user->about;
        $util = PhoneNumberUtil::getInstance();
        $this->phone = $user->phone ? $util->format($user->phone, PhoneNumberFormat::E164) : null;
        $this->roles = $user->roles->map(fn(Role $role) => $role->role)->toArray();
        $this->createdAt = $user->createdAt;
        $this->referer = $user->invite ? new UserInfoResponse($user->invite->author) : null;
        $this->deletedAt = $user->deletedAt;
        $this->bannedBy = $user->bannedBy ? new UserInfoResponse($user->bannedBy) : null;
        $this->deletedBy = $user->deletedBy ? new UserInfoResponse($user->deletedBy) : null;
        $this->deleteComment = $user->deleteComment;
        $this->banComment = $user->banComment;

        $lastAccessToken = $user->accessTokens
            ->matching(Criteria::create()->orderBy(['id' => Criteria::DESC]))
            ->map(fn (AccessToken $t) => new LastAccessTokenResponse($t))
            ->first();

        $this->lastAccessToken = $lastAccessToken ? $lastAccessToken : null;
        $this->banned = null !== $user->bannedAt;
    }
}
