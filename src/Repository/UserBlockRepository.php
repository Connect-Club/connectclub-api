<?php

namespace App\Repository;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\User;
use App\Entity\UserBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UserBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserBlock[]    findAll()
 * @method UserBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserBlockRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBlock::class);
    }

    public function findActualUserBlock(User $author, User $blockedUser): ?UserBlock
    {
        return $this->createQueryBuilder('ub')
                    ->where('ub.author = :author')
                    ->andWhere('ub.blockedUser = :blockedUser')
                    ->andWhere('ub.deletedAt IS NULL')
                    ->getQuery()
                    ->setMaxResults(1)
                    ->setFirstResult(0)
                    ->setParameter('blockedUser', $blockedUser)
                    ->setParameter('author', $author)
                    ->getOneOrNullResult();
    }
}
