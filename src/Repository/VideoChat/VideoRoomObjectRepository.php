<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomObject[]    findAll()
 * @method VideoRoomObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomObjectRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomObject::class);
    }
}
