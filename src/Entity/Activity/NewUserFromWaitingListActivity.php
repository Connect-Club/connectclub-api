<?php

namespace App\Entity\Activity;

use App\Entity\User;
use App\Repository\Activity\NewUserFromWaitingListActivityRepository;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;

/**
 * @ORM\Entity(repositoryClass=NewUserFromWaitingListActivityRepository::class)
 */
class NewUserFromWaitingListActivity extends Activity implements ActivityWithPhoneNumberInterface
{
    /** @ORM\Column(type="phone_number") */
    public PhoneNumber $phoneNumber;

    public function __construct(PhoneNumber $phoneNumber, User $user, User ...$users)
    {
        $this->phoneNumber = $phoneNumber;

        parent::__construct($user, ...$users);
    }

    public function getType(): string
    {
        return self::TYPE_NEW_USER_ASK_INVITE;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }
}
