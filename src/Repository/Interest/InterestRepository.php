<?php

namespace App\Repository\Interest;

use App\Entity\Interest\Interest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method Interest|null find($id, $lockMode = null, $lockVersion = null)
 * @method Interest|null findOneBy(array $criteria, array $orderBy = null)
 * @method Interest[]    findAll()
 * @method Interest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interest::class);
    }

    public function findByIds(array $ids, bool $isOld = true): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.id IN (:ids)')
            ->andWhere('i.isOld = :isOld')
            ->setParameter('ids', $ids)
            ->setParameter('isOld', $isOld)
            ->getQuery()
            ->getResult();
    }
}
