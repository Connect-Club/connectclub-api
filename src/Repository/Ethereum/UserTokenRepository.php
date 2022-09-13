<?php

namespace App\Repository\Ethereum;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Ethereum\UserToken;
use App\Entity\User;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UserToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserToken[]    findAll()
 * @method UserToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserTokenRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserToken::class);
    }

    public function findByUser(User $user, int $lastValue, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user->getId())
        ;

        return $this->getSimpleResult(
            UserToken::class,
            $qb->getQuery(),
            $lastValue,
            $limit,
            'token_id_0',
            'ASC'
        );
    }
}
