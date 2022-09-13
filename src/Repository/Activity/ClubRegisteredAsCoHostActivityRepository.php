<?php

namespace App\Repository\Activity;

use App\Entity\Activity\ClubRegisteredAsCoHostActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ClubRegisteredAsCoHostActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubRegisteredAsCoHostActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubRegisteredAsCoHostActivity[]    findAll()
 */
class ClubRegisteredAsCoHostActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubRegisteredAsCoHostActivity::class);
    }

    // /**
    //  * @return ClubRegisteredAsCoHostActivity[] Returns an array of ClubRegisteredAsCoHostActivity objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ClubRegisteredAsCoHostActivity
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
