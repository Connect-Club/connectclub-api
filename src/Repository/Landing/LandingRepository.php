<?php

namespace App\Repository\Landing;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Landing\Landing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Landing|null find($id, $lockMode = null, $lockVersion = null)
 * @method Landing|null findOneBy(array $criteria, array $orderBy = null)
 * @method Landing[]    findAll()
 * @method Landing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LandingRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Landing::class);
    }
}
