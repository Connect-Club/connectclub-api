<?php

namespace App\Repository\Activity;

use App\Entity\Activity\NewJoinRequestActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NewJoinRequestActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewJoinRequestActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewJoinRequestActivity[] findAll()
 * @method NewJoinRequestActivity[] findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class NewJoinRequestActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewJoinRequestActivity::class);
    }
}
