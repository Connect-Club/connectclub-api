<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomStaticObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomStaticObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomStaticObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomStaticObject[]    findAll()
 * @method VideoRoomStaticObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomStaticObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomStaticObject::class);
    }
}
