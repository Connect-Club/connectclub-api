<?php

namespace App\DTO\V1\Activity;

use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Activity\ActivityInterface;
use App\Entity\Activity\ClubActivityInterface;
use App\Entity\User;
use App\Entity\Activity\Activity;

class ActivityItemResponse
{
    /** @var string */
    public string $id;

    /** @var string */
    public string $type;

    /** @var string|null */
    public ?string $head = null;

    /** @var string */
    public string $title;

    /** @var UserInfoResponse[] */
    public array $relatedUsers;

    /** @var bool */
    public bool $new;

    /** @var int */
    public int $createdAt;

    /** @var string|null */
    public ?string $firstIcon = null;

    /** @var string|null */
    public ?string $secondIcon = null;

    public function __construct(ActivityInterface $activity, string $title)
    {
        $this->id = $activity->getId()->toString();
        $this->type = $activity->getType();
        $this->createdAt = $activity->getCreatedAt();
        $this->title = $title;
        $this->relatedUsers = $activity->getNestedUsers()->map(fn(User $u) => new UserInfoResponse($u))->getValues();
        $this->new = $activity->getReadAt() === null;
        /** @var User|null $nestedUser */
        $nestedUser = $activity->getNestedUsers()->first();
        if ($nestedUser) {
            $this->firstIcon = $nestedUser->getAvatarSrc();
        }
        if ($activity instanceof ClubActivityInterface) {
            $clubAvatar = $activity->getClub()->avatar;
            if ($clubAvatar) {
                $this->secondIcon = $clubAvatar->getResizerUrl();
            }
        }
        $this->createdAt = $activity->getCreatedAt();
    }
}
