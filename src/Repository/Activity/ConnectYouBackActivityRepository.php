<?php

namespace App\Repository\Activity;

use App\Entity\Activity\ConnectYouBackActivity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ConnectYouBackActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ConnectYouBackActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ConnectYouBackActivity[]    findAll()
 * @method ConnectYouBackActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConnectYouBackActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnectYouBackActivity::class);
    }

    public function findActivity(User $follower, User $user): ?ConnectYouBackActivity
    {
        return $this->createQueryBuilder('a')
                    ->join('a.nestedUsers', 'n')
                    ->where('a.user = :user')
                    ->andWhere('n.id = :follower')
                    ->setParameter('user', $user)
                    ->setParameter('follower', $follower)
                    ->getQuery()
                    ->getOneOrNullResult();
    }
}
