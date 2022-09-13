<?php

namespace App\Entity\Photo;

use App\Entity\User;
use App\Util\ConnectClub;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table("photo")
 * @ORM\MappedSuperclass()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "userAvatar"      = "UserPhoto",
 *     "videoRoomBackground" = "App\Entity\VideoChat\BackgroundPhoto",
 *     "videoRoomImageObject" = "App\Entity\Photo\VideoRoomImageObjectPhoto",
 *     "image" = "App\Entity\Photo\Image",
 *     "nftImage" = "App\Entity\Photo\NftImage",
 * })
 */
abstract class AbstractPhoto
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     *
     * @Groups({"v1.upload.default_photo"})
     */
    public ?int $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"v1.upload.user_photo", "v1.upload.default_photo", "v1.upload.default_photo"})
     */
    public string $originalName;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"v1.upload.user_photo", "v1.upload.default_photo", "v1.account.current"})
     */
    public string $processedName;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"v1.upload.user_photo", "v1.upload.default_photo", "v1.account.current"})
     */
    public string $bucket;

    /**
     * @var int
     * @ORM\Column(type="bigint")
     * @Groups({"v1.upload.user_photo", "v1.upload.default_photo"})
     */
    public int $uploadAt;

    /**
     * @var int|null
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"default"})
     */
    public ?int $width = null;

    /**
     * @var int|null
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"default"})
     */
    public ?int $height = null;

    /**
     * @var User|UserInterface
     * @ORM\JoinColumn(nullable=false)
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public UserInterface $uploadBy;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $checkedAt = null;

    /**
     * AbstractPhoto constructor.
     */
    public function __construct(string $bucket, string $originalSrc, string $src, UserInterface $uploadBy)
    {
        $this->bucket = $bucket;
        $this->originalName = $originalSrc;
        $this->processedName = $src;
        $this->uploadBy = $uploadBy;
        $this->uploadAt = time();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @Groups({"v1.room.default"})
     */
    public function getOriginalUrl(): string
    {
        return sprintf('https://storage.googleapis.com/%s/%s', $this->bucket, $this->originalName);
    }

    /**
     * @Groups({"v1.room.default", "v1.upload.user_photo"})
     */
    public function getResizerUrl($width = ':WIDTH', $height = ':HEIGHT'): string
    {
        return ConnectClub::getResizerUrl($this, $width, $height);
    }

    public function getResizerCropUrl(): string
    {
        return ConnectClub::getResizerCropUrl($this);
    }
}
