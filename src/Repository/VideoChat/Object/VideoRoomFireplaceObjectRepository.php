<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomFireplaceObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomFireplaceObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomFireplaceObject[]    findAll()
 * @method VideoRoomFireplaceObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomFireplaceObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomFireplaceObject::class);
    }
}
