<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomVideoObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomVideoObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomVideoObject[]    findAll()
 * @method VideoRoomVideoObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomVideoObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomVideoObject::class);
    }
}
