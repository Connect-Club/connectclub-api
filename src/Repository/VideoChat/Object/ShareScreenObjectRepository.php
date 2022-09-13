<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\ShareScreenObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ShareScreenObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShareScreenObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShareScreenObject[]    findAll()
 * @method ShareScreenObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShareScreenObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareScreenObject::class);
    }

    // /**
    //  * @return ShareScreenObject[] Returns an array of ShareScreenObject objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ShareScreenObject
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
