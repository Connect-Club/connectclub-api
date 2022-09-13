<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomPortalObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomPortalObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomPortalObject[]    findAll()
 * @method VideoRoomPortalObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomPortalObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomPortalObject::class);
    }
}
