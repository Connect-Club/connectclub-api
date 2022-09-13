<?php

namespace App\Repository\Activity;

use App\Entity\Activity\NewClubInviteActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NewClubInviteActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewClubInviteActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewClubInviteActivity[]    findAll()
 * @method NewClubInviteActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewClubInviteActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewClubInviteActivity::class);
    }

    // /**
    //  * @return NewClubInviteActivity[] Returns an array of NewClubInviteActivity objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?NewClubInviteActivity
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
