<?php

namespace App\Entity\VideoChat;

use App\Entity\Photo\AbstractPhoto;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\BackgroundPhotoRepository")
 */
class BackgroundPhoto extends AbstractPhoto
{
    /**
     * @var VideoRoomConfig[]|Collection
     * @ORM\OneToMany(targetEntity="App\Entity\VideoChat\VideoRoomConfig", mappedBy="backgroundRoom")
     */
    public Collection $videoRooms;

    /**
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\VideoChat\VideoRoomObject",
     *     mappedBy="background",
     *     cascade={"all"},
     *     orphanRemoval=true,
     * )
     */
    public Collection $objects;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $isSystemBackground = false;

    public function __construct(
        string $bucket,
        string $originalSrc,
        string $src,
        int $width,
        int $height,
        UserInterface $uploadBy
    ) {
        $this->videoRooms = new ArrayCollection();
        $this->objects = new ArrayCollection();
        $this->width = $width;
        $this->height = $height;

        $spawnLocation = new Location();
        $spawnLocation->x = 500;
        $spawnLocation->y = 1500;

        $this->objects->add(new VideoRoomMainSpawnObject(
            null,
            $this,
            $spawnLocation,
            1000,
            1500
        ));

        parent::__construct($bucket, $originalSrc, $src, $uploadBy);
    }
}
