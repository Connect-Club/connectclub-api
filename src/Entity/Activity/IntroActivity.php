<?php

namespace App\Entity\Activity;

use App\Repository\Activity\IntroActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=IntroActivityRepository::class)
 */
class IntroActivity extends Activity
{
    public function getType(): string
    {
        return self::TYPE_INTRO;
    }
}
