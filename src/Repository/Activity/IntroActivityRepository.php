<?php

namespace App\Repository\Activity;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Activity\IntroActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IntroActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method IntroActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method IntroActivity[]    findAll()
 * @method IntroActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IntroActivityRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntroActivity::class);
    }
}
