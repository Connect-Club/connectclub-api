<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoChat\VideoRoomConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomConfig[]    findAll()
 * @method VideoRoomConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomConfigRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomConfig::class);
    }
}
