<?php

namespace App\Entity\Activity;

use App\Repository\Activity\UserRegisteredActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UserRegisteredActivityRepository::class)
 */
class UserRegisteredActivity extends Activity
{
    public function getType(): string
    {
        return self::TYPE_USER_REGISTERED;
    }
}
