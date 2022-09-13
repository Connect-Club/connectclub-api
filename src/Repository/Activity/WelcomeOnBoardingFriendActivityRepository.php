<?php

namespace App\Repository\Activity;

use App\Entity\Activity\WelcomeOnBoardingFriendActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WelcomeOnBoardingFriendActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method WelcomeOnBoardingFriendActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method WelcomeOnBoardingFriendActivity[]    findAll()
 * @method WelcomeOnBoardingFriendActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class WelcomeOnBoardingFriendActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WelcomeOnBoardingFriendActivity::class);
    }
}
