<?php

namespace App\Repository\Event;

use App\Entity\Event\EventDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EventDraft|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventDraft|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventDraft[]    findAll()
 * @method EventDraft[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventDraft::class);
    }

    // /**
    //  * @return EventDraft[] Returns an array of EventDraft objects
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
    public function findOneBySomeField($value): ?EventDraft
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
