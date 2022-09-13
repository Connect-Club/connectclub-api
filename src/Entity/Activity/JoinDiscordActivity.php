<?php

namespace App\Entity\Activity;

use App\ConnectClub;
use App\Repository\Activity\JoinDiscordActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=JoinDiscordActivityRepository::class)
 */
class JoinDiscordActivity extends Activity
{
    public function getType(): string
    {
        return Activity::TYPE_JOIN_DISCORD_COMMUNITY;
    }

    public function getLink(): ?string
    {
        return ConnectClub::getDiscordLink();
    }
}
