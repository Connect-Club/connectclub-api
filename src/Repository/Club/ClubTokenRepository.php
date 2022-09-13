<?php

namespace App\Repository\Club;

use App\Entity\Club\Club;
use App\Entity\Club\ClubToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ClubToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubToken[]    findAll()
 * @method ClubToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClubTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubToken::class);
    }

    public function findClubTokensForClubIdAndTokenIds(Club $club, array $tokenIds): array
    {
        return $this->createQueryBuilder('ct')
                    ->addSelect('c')
                    ->addSelect('t')
                    ->join('ct.club', 'c')
                    ->join('ct.token', 't')
                    ->where('ct.club = :club')
                    ->andWhere('t.id IN (:tokenIds)')
                    ->getQuery()
                    ->setParameter('club', $club)
                    ->setParameter('tokenIds', $tokenIds)
                    ->getResult();
    }

    public function findClubTokensForTokenId(string $tokenId): array
    {
        return $this->createQueryBuilder('ct')
            ->join('ct.club', 'c')
            ->addSelect('c')
            ->join('ct.token', 't')
            ->where('t.tokenId = :tokenId')
            ->setParameter('tokenId', $tokenId)
            ->getQuery()
            ->getResult();
    }
}
