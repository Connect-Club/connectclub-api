<?php

namespace App\Repository\Activity;

use App\Entity\Activity\StartedVideoRoomActivity;
use App\Repository\BulkInsertTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StartedVideoRoomActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method StartedVideoRoomActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method StartedVideoRoomActivity[]    findAll()
 * @method StartedVideoRoomActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StartedVideoRoomActivityRepository extends ServiceEntityRepository
{
    use BulkInsertTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StartedVideoRoomActivity::class);
    }
}
