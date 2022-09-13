<?php

namespace App\Repository\Activity;

use App\Entity\Activity\StartedClubVideoRoomActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StartedClubVideoRoomActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method StartedClubVideoRoomActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method StartedClubVideoRoomActivity[] findAll()
 * @method StartedClubVideoRoomActivity[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StartedClubVideoRoomActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StartedClubVideoRoomActivity::class);
    }
}
