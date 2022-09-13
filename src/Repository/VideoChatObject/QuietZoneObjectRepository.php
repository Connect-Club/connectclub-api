<?php

namespace App\Repository\VideoChatObject;

use App\Entity\VideoChatObject\QuietZoneObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method QuietZoneObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuietZoneObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method QuietZoneObject[]    findAll()
 * @method QuietZoneObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuietZoneObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuietZoneObject::class);
    }

    // /**
    //  * @return QuietZoneObject[] Returns an array of QuietZoneObject objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('q.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?QuietZoneObject
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
