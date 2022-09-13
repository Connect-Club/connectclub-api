<?php

namespace App\Repository\Club;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Activity\Activity;
use App\Entity\Club\Club;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method JoinRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method JoinRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method JoinRequest[]    findAll()
 * @method JoinRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JoinRequestRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoinRequest::class);
    }

    public function findModerationJoinRequest(Club $club, User $user): ?JoinRequest
    {
        return $this->findOneBy([
            'club' => $club,
            'author' => $user,
            'status' => [JoinRequest::STATUS_MODERATION]
        ]);
    }

    public function findForCurrentUser(User $user, int $lastValue, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('joinRequest')
            ->join('joinRequest.club', 'club')
            ->join('joinRequest.author', 'author')
            ->where('joinRequest.author = :user')
            ->andWhere('author.state NOT IN (:states)')
            ->andWhere('joinRequest.status NOT IN (:statuses)')
            ->setParameter('states', [User::STATE_BANNED, User::STATE_DELETED])
            ->setParameter('statuses', [JoinRequest::STATUS_CANCELLED])
            ->setParameter('user', $user);

        return $this->getSimpleResult(
            JoinRequest::class,
            $query->getQuery(),
            $lastValue,
            $limit,
            'created_at_3',
            'DESC, id_0 ASC'
        );
    }

    public function findJoinRequestsForClub(Club $club, ?array $userIds, int $lastValue, int $limit = 20): array
    {
        if (null !== $userIds && 0 === count($userIds)) {
            return [[], $lastValue, $limit];
        }

        $query = $this->createQueryBuilder('j')
                      ->where('j.club = :club')
                      ->andWhere('j.status = :status')
                      ->setParameter('status', JoinRequest::STATUS_MODERATION)
                      ->setParameter('club', $club);
        if (null !== $userIds) {
            $query->andWhere('j.author IN (:userIds)')
                  ->setParameter('userIds', $userIds);
        }


        return $this->getSimpleResult(Activity::class, $query->getQuery(), $lastValue, $limit, 'created_at_3', 'DESC');
    }

    public function findJoinRequestsOfUserOfClubIds(User $author, array $clubIds): array
    {
        if (!$clubIds) {
            return [];
        }

        return $this->createQueryBuilder('j')
                    ->join('j.club', 'c')
                    ->where('c.id IN (:clubsIds)')
                    ->andWhere('j.author = :author')
                    ->setParameter('clubsIds', $clubIds)
                    ->setParameter('author', $author)
                    ->getQuery()
                    ->getResult();
    }
}
