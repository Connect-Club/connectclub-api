<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoChat\VideoRoomEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VideoRoomEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomEvent[]    findAll()
 * @method VideoRoomEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomEventRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomEvent::class);
    }
}
