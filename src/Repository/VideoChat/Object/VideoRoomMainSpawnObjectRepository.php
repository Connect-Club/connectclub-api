<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomMainSpawnObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomMainSpawnObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomMainSpawnObject[]    findAll()
 * @method VideoRoomMainSpawnObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomMainSpawnObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomMainSpawnObject::class);
    }
}
