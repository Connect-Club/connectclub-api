<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomSquarePortalObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomSquarePortalObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomSquarePortalObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomSquarePortalObject[] findAll()
 * @method VideoRoomSquarePortalObject[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomSquarePortalObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomSquarePortalObject::class);
    }
}
