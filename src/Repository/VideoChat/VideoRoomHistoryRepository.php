<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoChat\VideoRoomHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomHistory[]    findAll()
 * @method VideoRoomHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomHistoryRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomHistory::class);
    }
}
