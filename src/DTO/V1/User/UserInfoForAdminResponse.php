<?php

namespace App\DTO\V1\User;

use App\DTO\V2\User\FullUserInfoResponse;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\User\Device;
use libphonenumber\PhoneNumber;

class UserInfoForAdminResponse extends FullUserInfoResponse
{
    /** @var int */
    public int $freeInvites = 0;

    /** @var string */
    public string $state;

    /** @var UserInfoResponse|null  */
    public ?UserInfoResponse $bannedBy = null;

    /** @var UserInfoResponse|null  */
    public ?UserInfoResponse $deletedBy = null;

    /** @var string|null */
    public ?string $deleteComment = null;

    /** @var string|null */
    public ?string $banComment = null;

    /** @var PhoneNumber|null */
    public ?PhoneNumber $phone = null;

    /** @var string[] */
    public array $devices = [];

    /** @var string|null */
    public ?string $city;

    /** @var string|null */
    public ?string $country;

    /** @var string|null */
    public ?string $source = null;

    public function __construct(User $user, bool $isFollowing, bool $isFollows, int $followers, int $following)
    {
        parent::__construct($user, $isFollowing, $isFollows, $followers, $following);

        $this->id = (string) $user->id;
        $this->avatar = $user->getAvatarSrc();
        $this->name = $user->name;
        $this->surname = $user->surname;
        $this->displayName = $user->getFullNameOrId();
        $this->about = $user->about ?? '';
        $this->username = $user->username ?? '';
        $this->isDeleted = $user->deleted || $user->bannedAt !== null;
        $this->createdAt = $user->createdAt;
        $this->online = $user->isOnline();
        $this->lastSeen = $user->lastTimeActivity ?? $user->createdAt;

        $this->freeInvites = $user->freeInvites;
        $this->state = $user->state;
        $this->bannedBy = $user->bannedBy ? new UserInfoResponse($user->bannedBy) : null;
        $this->deletedBy = $user->deletedBy ? new UserInfoResponse($user->deletedBy) : null;
        $this->deleteComment = $user->deleteComment;
        $this->banComment = $user->banComment;

        $this->city = $user->city ? $user->city->name : null;
        $this->country = $user->country ? $user->country->isoCode : null;

        $this->phone = $user->phone;
        $this->devices = $user->devices->map(function (Device $device) {
            $model = $device->model === null ? '' : $device->model;
            return "$device->type: $model";
        })->toArray();

        $this->source = $user->source;
    }
}
