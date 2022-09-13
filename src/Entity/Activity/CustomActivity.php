<?php

namespace App\Entity\Activity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Activity\CustomActivityRepository")
 */
class CustomActivity extends Activity
{
    /** @ORM\Column(type="string", nullable=true) */
    public ?string $title = null;

    /** @ORM\Column(type="string") */
    public string $text;

    /** @ORM\Column(type="string", nullable=true) */
    private ?string $externalLink;

    public function __construct(?string $title, string $text, ?string $externalLink, User $user, User ...$users)
    {
        parent::__construct($user, ...$users);

        $this->title = $title;
        $this->text = $text;
        $this->externalLink = $externalLink;
    }

    public function getType(): string
    {
        return Activity::TYPE_CUSTOM;
    }

    public function getExternalLink(): ?string
    {
        return $this->externalLink;
    }
}
