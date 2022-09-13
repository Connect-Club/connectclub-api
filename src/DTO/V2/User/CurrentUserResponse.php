<?php

namespace App\DTO\V2\User;

use App\DTO\V1\Reference\ReferenceResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\Entity\Interest\Interest;
use App\Entity\Matching\ReferenceInterface;
use App\Entity\Role;
use App\Entity\User;

class CurrentUserResponse
{
    /** @var string */
    public string $id;

    /** @var string|null */
    public ?string $username = null;

    /** @var string|null */
    public ?string $name = null;

    /** @var string|null */
    public ?string $surname = null;

    /** @var string */
    public string $about;

    /** @var string|null */
    public ?string $avatar;

    /** @var string */
    public string $state;

    /** @var UserInfoResponse|null */
    public ?UserInfoResponse $joinedBy = null;

    /** @var string|null */
    public ?string $joinedByClubRole = null;

    /** @var InterestDTO[] */
    public array $interests;

    /** @var ReferenceResponse[] */
    public array $skills = [];

    /** @var ReferenceResponse[] */
    public array $goals = [];

    /** @var ReferenceResponse[] */
    public array $industries = [];

    /** @var int|null */
    public ?int $skipNotificationUntil = null;

    /** @var string[] */
    public array $badges;

    /** @var string|null */
    public ?string $shortBio = null;

    /** @var string|null */
    public ?string $longBio = null;

    /** @var string|null */
    public ?string $twitter = null;

    /** @var string|null */
    public ?string $linkedin = null;

    /** @var string|null */
    public ?string $instagram = null;

    /** @var string|null */
    public ?string $wallet = null;

    /** @var LanguageDTO|null */
    public ?LanguageDTO $language = null;

    /** @var LanguageDTO[] */
    public array $languages = [];

    /** @var bool */
    public bool $isSuperCreator;

    /** @var bool */
    public bool $enableDeleteWallet = false;

    /** @var int */
    public int $createdAt;

    public function __construct(User $user, ?string $invitedByClubRole = null)
    {
        $this->id = (string) $user->id;
        $this->username = $user->username;
        $this->name = $user->name;
        $this->surname = $user->surname;
        $this->about = (string) $user->about;
        $this->avatar = $user->getAvatarSrc();
        $this->state = $user->state;
        $this->interests = $user->interests->filter(
            fn(Interest $i) => !$i->isOld
        )->map(
            fn(Interest $i) => new InterestDTO($i)
        )->getValues();

        $invitedBy = $user->invite ? $user->invite->author : null;
        if ($invitedBy) {
            $this->joinedBy = new UserInfoResponse($invitedBy);
        }

        $this->goals = array_map(fn(ReferenceInterface $r) => new ReferenceResponse($r), $user->goals->toArray());
        $this->industries = array_map(
            fn(ReferenceInterface $r) => new ReferenceResponse($r),
            $user->industries->toArray()
        );
        $this->skills = array_map(fn(ReferenceInterface $r) => new ReferenceResponse($r), $user->skills->toArray());

        $this->skipNotificationUntil = $user->skipNotificationUntil;
        $this->badges = $user->badges ?? [];
        $language = $user->nativeLanguages->first();
        $this->language = $language ? new LanguageDTO($language) : null;
        $this->languages = array_map(fn(User\Language $i) => new LanguageDTO($i), $user->nativeLanguages->toArray());
        $this->shortBio = $user->shortBio;
        $this->isSuperCreator = $user->hasRole(Role::ROLE_SUPERCREATOR);
        $this->longBio = $user->longBio;

        $this->twitter = $user->twitter;
        $this->linkedin = $user->linkedin;
        $this->instagram = $user->instagram;

        $this->wallet = $user->wallet;

        $this->joinedByClubRole = $invitedByClubRole;

        $this->enableDeleteWallet = $user->phone !== null;
        $this->createdAt = $user->createdAt;
    }
}
