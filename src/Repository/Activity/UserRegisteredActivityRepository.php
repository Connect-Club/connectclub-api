<?php

namespace App\Repository\Activity;

use App\Entity\Activity\UserRegisteredActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UserRegisteredActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRegisteredActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserRegisteredActivity[]    findAll()
 * @method UserRegisteredActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRegisteredActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRegisteredActivity::class);
    }
}
