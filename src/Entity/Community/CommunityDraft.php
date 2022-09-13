<?php

namespace App\Entity\Community;

use App\Entity\VideoChat\BackgroundPhoto;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Community\CommunityDraftRepository")
 */
class CommunityDraft
{
    const TYPE_PUBLIC = 'public';
    const TYPE_PRIVATE = 'private';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id;

    /**
     * @ORM\Column(type="string")
     */
    public string $description;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\BackgroundPhoto")
     */
    public BackgroundPhoto $communityPhoto;

    /**
     * @ORM\Column(type="integer")
     */
    public int $backgroundRoomWidthMultiplier;

    /**
     * @ORM\Column(type="integer", options={"default": 2})
     */
    public int $backgroundRoomHeightMultiplier = 2;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    public int $index;

    /**
     * @var string
     * @ORM\Column(type="string", options={"default": CommunityDraft::TYPE_PUBLIC})
     */
    public string $type = self::TYPE_PUBLIC;

    public function __construct(
        string $description,
        BackgroundPhoto $photo,
        int $backgroundRoomWidthMultiplier,
        int $index
    ) {
        $this->description = $description;
        $this->communityPhoto = $photo;
        $this->backgroundRoomWidthMultiplier = $backgroundRoomWidthMultiplier;
        $this->index = $index;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
