<?php

namespace App\Repository\Activity;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Activity\InvitePrivateVideoRoomActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InvitePrivateVideoRoomActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method InvitePrivateVideoRoomActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method InvitePrivateVideoRoomActivity[]    findAll()
 * @method InvitePrivateVideoRoomActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class InvitePrivateVideoRoomActivityRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvitePrivateVideoRoomActivity::class);
    }
}
