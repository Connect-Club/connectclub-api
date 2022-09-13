<?php

namespace App\Repository\Log;

use App\Entity\Log\EventLogRelation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EventLogRelation|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventLogRelation|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventLogRelation[]    findAll()
 * @method EventLogRelation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventLogRelationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventLogRelation::class);
    }

    // /**
    //  * @return EventLogRelation[] Returns an array of EventLogRelation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?EventLogRelation
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
