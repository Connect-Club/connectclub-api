<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomImageZoneObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VideoRoomImageZoneObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomImageZoneObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomImageZoneObject[]    findAll()
 * @method VideoRoomImageZoneObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomImageZoneObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomImageZoneObject::class);
    }
}
