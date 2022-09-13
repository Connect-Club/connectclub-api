<?php

namespace App\Entity\Photo;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Photo\ImageRepository")
 */
class Image extends AbstractPhoto
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     * @Groups({"v1.upload.user_photo"})
     */
    public ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
