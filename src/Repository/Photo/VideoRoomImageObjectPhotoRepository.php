<?php

namespace App\Repository\Photo;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Photo\VideoRoomImageObjectPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VideoRoomImageObjectPhoto|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomImageObjectPhoto|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomImageObjectPhoto[]    findAll()
 * @method VideoRoomImageObjectPhoto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomImageObjectPhotoRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomImageObjectPhoto::class);
    }
}
