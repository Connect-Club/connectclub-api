<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomImageObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VideoRoomImageObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomImageObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomImageObject[]    findAll()
 * @method VideoRoomImageObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomImageObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomImageObject::class);
    }
}
