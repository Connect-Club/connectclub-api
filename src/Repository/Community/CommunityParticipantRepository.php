<?php

namespace App\Repository\Community;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Community\CommunityParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CommunityParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityParticipant[]    findAll()
 * @method CommunityParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityParticipantRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityParticipant::class);
    }

    public function createQueryMyCommunities(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->addSelect('c')
            ->addSelect('v')
            ->addSelect('g')
            ->addSelect('m')
            ->addSelect('p')
            ->addSelect('u')
            ->addSelect('o')
            ->addSelect('a')
            ->addSelect('i')
            ->leftJoin('e.community', 'c')
            ->leftJoin('c.videoRoom', 'v')
            ->leftJoin('v.groupChat', 'g')
            ->leftJoin('v.meetings', 'm', Join::WITH, 'm.endTime IS NULL')
            ->leftJoin('m.participants', 'p')
            ->leftJoin('p.participant', 'u')
            ->leftJoin('c.owner', 'o')
            ->leftJoin('o.avatar', 'a')
            ->leftJoin('o.interests', 'i')
            ->where('e.user = :user')
            ->setParameter('user', $user);
    }

    /** @return CommunityParticipant[] */
    public function findDeletedParticipantsInPublicCommunities()
    {
        return $this->createQueryBuilder('p')
            ->join('p.community', 'c')
            ->join('p.user', 'u')
            ->where('u.deletedAt IS NOT NULL')
            ->andWhere('c.isPublic = true')
            ->getQuery()
            ->getResult();
    }
}
