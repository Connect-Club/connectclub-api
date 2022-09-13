<?php

namespace App\Entity\Activity;

use App\Repository\Activity\ConnectYouBackActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConnectYouBackActivityRepository::class)
 */
class ConnectYouBackActivity extends Activity
{
    public function getType(): string
    {
        return self::TYPE_CONNECT_YOU_BACK;
    }
}
