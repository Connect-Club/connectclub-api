<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VideoRoomNftImageObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomNftImageObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomNftImageObject[]    findAll()
 * @method VideoRoomNftImageObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomNftImageObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomNftImageObject::class);
    }
}
