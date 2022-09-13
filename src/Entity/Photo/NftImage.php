<?php

namespace App\Entity\Photo;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Photo\NftImageRepository;

/**
 * @ORM\Entity(repositoryClass=NftImageRepository::class)
 */
class NftImage extends AbstractPhoto
{
}
