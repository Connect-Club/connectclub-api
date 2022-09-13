<?php

namespace App\Repository\Activity;

use App\Entity\Activity\RegisteredAsCoHostActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RegisteredAsCoHostActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegisteredAsCoHostActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegisteredAsCoHostActivity[]    findAll()
 * @method RegisteredAsCoHostActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegisteredAsCoHostActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegisteredAsCoHostActivity::class);
    }

    // /**
    //  * @return RegisteredAsCoHostActivity[] Returns an array of RegisteredAsCoHostActivity objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?RegisteredAsCoHostActivity
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
