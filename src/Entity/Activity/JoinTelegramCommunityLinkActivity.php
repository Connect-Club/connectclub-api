<?php

namespace App\Entity\Activity;

use App\ConnectClub;
use App\Entity\User;
use App\Repository\Activity\JoinTelegramCommunityLinkActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=JoinTelegramCommunityLinkActivityRepository::class)
 */
class JoinTelegramCommunityLinkActivity extends CustomActivity
{
    public function __construct(User $user)
    {
        parent::__construct(
            'Join our community chat',
            'You can find link to community in your profile settings',
            null,
            $user
        );
    }

    public function getExternalLink(): ?string
    {
        return ConnectClub::getTelegramChannelForLanguage($this->user);
    }
}
