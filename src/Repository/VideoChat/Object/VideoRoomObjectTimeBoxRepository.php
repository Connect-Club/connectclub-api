<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomObjectTimeBox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VideoRoomObjectTimeBox|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomObjectTimeBox|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomObjectTimeBox[]    findAll()
 * @method VideoRoomObjectTimeBox[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomObjectTimeBoxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomObjectTimeBox::class);
    }

    // /**
    //  * @return VideoRoomObjectTimeBox[] Returns an array of VideoRoomObjectTimeBox objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?VideoRoomObjectTimeBox
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
