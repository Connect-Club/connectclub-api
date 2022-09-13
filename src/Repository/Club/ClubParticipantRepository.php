<?php

namespace App\Repository\Club;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\DTO\V1\Subscription\Event;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidType;

/**
 * @method ClubParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubParticipant[]    findAll()
 * @method ClubParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClubParticipantRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubParticipant::class);
    }

    public function findClubParticipant(User $user, int $limit, $lastValue = null): array
    {
        $query = $this->createQueryBuilder('cp')
            ->join('cp.club', 'c')
            ->where('cp.user = :user')
            ->setParameter('user', $user)
            ->getQuery();

        return $this->getSimpleResult(
            EventSchedule::class,
            $query,
            $lastValue,
            $limit,
            'joined_at_2',
            'DESC'
        );
    }

    public function findParticipantsCountForClub(Club $club): int
    {
        return (int) $this->createQueryBuilder('e')
                          ->select('COUNT(DISTINCT e.id)')
                          ->join('e.user', 'u')
                          ->where('e.club = :club')
                          ->andWhere('u.state = :verified')
                          ->setParameter('club', $club)
                          ->setParameter('verified', User::STATE_VERIFIED)
                          ->getQuery()
                          ->getSingleScalarResult();
    }

    public function findClubParticipants(Club $club): array
    {
        return $this->createQueryBuilder('cp')
                    ->addSelect('u')
                    ->join('cp.user', 'u')
                    ->where('cp.club = :club')
                    ->setParameter('club', $club)
                    ->getQuery()
                    ->getResult();
    }

    /**
     * @return ClubParticipant[]
     */
    public function findPreviewParticipantsByClub(Club $club, int $limit): array
    {
        return $this->createQueryBuilder('clubParticipant')
            ->addSelect('user')
            ->join('clubParticipant.user', 'user')
            ->where('clubParticipant.club = :club')
            ->andWhere('user.deletedAt IS NULL')
            ->andWhere('user.state = :stateVerified')
            ->setMaxResults($limit)
            ->setParameter('club', $club)
            ->setParameter('stateVerified', User::STATE_VERIFIED)
            ->getQuery()
            ->getResult();
    }

    public function fetchEventScheduleParticipantInformation(EventSchedule $eventSchedule): array
    {
        return $this->fetchEventSchedulesParticipantInformation(
            [$eventSchedule->id->toString()]
        )[$eventSchedule->id->toString()] ?? [];
    }

    public function fetchEventSchedulesParticipantInformation(array $eventSchedulesIds): array
    {
        $sql = <<<SQL
        SELECT es.id AS event_schedule_id, cp.user_id, cp.role 
        FROM club_participant cp 
        JOIN club c ON c.id = cp.club_id
        JOIN event_schedule es ON es.club_id = c.id
        WHERE es.id IN (:eventScheduleIds)
        AND cp.user_id IN (
            SELECT esp.user_id FROM event_schedule_participant esp WHERE esp.event_id = es.id
        )
        SQL;

        $em = $this->getEntityManager();
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('event_schedule_id', 'event_schedule_id', Types::STRING);
        $rsm->addScalarResult('user_id', 'user_id', Types::INTEGER);
        $rsm->addScalarResult('role', 'role', Types::STRING);

        $data = $em->createNativeQuery($sql, $rsm)
                   ->setParameter('eventScheduleIds', $eventSchedulesIds)
                   ->getArrayResult();

        $items = [];
        foreach ($data as $item) {
            $items[$item['event_schedule_id']] ??= [];
            $items[$item['event_schedule_id']][] = $item;
        }

        return array_map(
            fn(array $item) => array_combine(
                array_map(fn(array $item) => $item['user_id'], $item),
                array_map(fn(array $item) => $item['role'], $item),
            ),
            $items
        );
    }
}
