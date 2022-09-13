<?php

namespace App\Entity\Activity;

use App\Repository\Activity\NewFollowerActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NewFollowerActivityRepository::class)
 */
class NewFollowerActivity extends Activity
{
    public function getType(): string
    {
        return self::TYPE_NEW_FOLLOWER;
    }
}
