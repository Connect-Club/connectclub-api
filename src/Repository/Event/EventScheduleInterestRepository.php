<?php

namespace App\Repository\Event;

use App\Entity\Event\EventScheduleInterest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EventScheduleInterest|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventScheduleInterest|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventScheduleInterest[]    findAll()
 * @method EventScheduleInterest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventScheduleInterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventScheduleInterest::class);
    }

    // /**
    //  * @return EventScheduleInterest[] Returns an array of EventScheduleInterest objects
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
    public function findOneBySomeField($value): ?EventScheduleInterest
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
