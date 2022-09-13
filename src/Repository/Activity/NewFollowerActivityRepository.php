<?php

namespace App\Repository\Activity;

use App\Entity\Activity\NewFollowerActivity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NewFollowerActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewFollowerActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewFollowerActivity[]    findAll()
 * @method NewFollowerActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewFollowerActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewFollowerActivity::class);
    }

    public function findActivity(User $follower, User $user): ?NewFollowerActivity
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
