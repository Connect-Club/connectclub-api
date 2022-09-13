<?php

namespace App\Entity\Activity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use App\Repository\Activity\NewUserRegisteredByInviteCodeActivityRepository;

/**
 * @ORM\Entity(repositoryClass=NewUserRegisteredByInviteCodeActivityRepository::class)
 */
class NewUserRegisteredByInviteCodeActivity extends Activity
{
    public function __construct(User $user, User ...$users)
    {
        parent::__construct($user, ...$users);
    }

    public function getType(): string
    {
        return self::TYPE_NEW_USER_REGISTERED_BY_INVITE_CODE;
    }
}
