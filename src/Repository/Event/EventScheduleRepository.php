<?php

namespace App\Repository\Event;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Doctrine\SQL\Snippet\SQLEventScheduleMutualLanguagesSnippet;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Repository\BulkInsertTrait;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @method EventSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventSchedule[]    findAll()
 * @method EventSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventScheduleRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;
    use BulkInsertTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventSchedule::class);
    }

    public function findEventSchedulesForClub(Club $club, int $limit = 20, ?int $lastValue = null): array
    {
        $qb = $this->createQueryBuilder('es');

        $query = $qb
            ->addSelect('vr')
            ->addSelect('esp')
            ->addSelect('u')
            ->addSelect('i')
//            ->addSelect('a')
            ->leftJoin('es.videoRoom', 'vr')
            ->leftJoin('es.participants', 'esp')
            ->leftJoin('esp.user', 'u')
            ->leftJoin('u.avatar', 'a')
            ->leftJoin('u.invite', 'i')
            ->where('es.club = :club')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('vr.id'),
                    $qb->expr()->isNull('vr.doneAt')
                )
            )
            ->andWhere('es.forMembersOnly = false')
            ->andWhere('es.dateTime >= '.(time() - 3600))
            ->orderBy('es.dateTime', 'ASC')
            ->setParameter('club', $club)
            ->getQuery();

        return $this->getSimpleResult(EventSchedule::class, $query, $lastValue, $limit, 'end_date_time_7', 'ASC');
    }

    public function findEventSchedulesForFestival(
        int $lastValue,
        int $limit,
        ?int $speakerId = null,
        ?string $festivalCode = null,
        ?string $festivalSceneId = null,
        ?int $dateStart = null,
        ?int $dateEnd = null,
        ?string $clubId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
                   ->addSelect('s')
                   ->addSelect('p')
                   ->addSelect('pu')
                   ->addSelect('pui')
                   ->addSelect('pua')
                   ->addSelect('v')
                   ->addSelect('esi')
                   ->addSelect('pui2')
                   ->addSelect('c')
                   ->leftJoin('a.interests', 'esi')
                   ->leftJoin('a.festivalScene', 's')
                   ->leftJoin('a.participants', 'p')
                   ->leftJoin('p.user', 'pu')
                   ->leftJoin('pu.interests', 'pui')
                   ->leftJoin('pu.avatar', 'pua')
                   ->leftJoin('a.videoRoom', 'v')
                   ->leftJoin('pu.invite', 'pui2')
                   ->leftJoin('a.club', 'c')
        ;

        $qb = $qb->where(
            $festivalCode ?
                $qb->expr()->eq('a.festivalCode', ':festivalCode') :
                $qb->expr()->isNotNull('a.festivalCode')
        );

        if ($festivalCode) {
            $qb->setParameter('festivalCode', $festivalCode);
        }

        if ($festivalSceneId) {
            $qb = $qb->andWhere(
                $qb->expr()->eq('s.id', ':festivalSceneId')
            )->setParameter('festivalSceneId', $festivalSceneId);
        }

        if ($dateStart) {
            if ($dateEnd) {
                $qb = $qb->andWhere(
                    $qb->expr()->gte('a.dateTime', ':dateStart')
                )->andWhere(
                    $qb->expr()->lte('a.endDateTime', ':dateEnd')
                )->setParameter(
                    'dateStart',
                    $dateStart
                )->setParameter(
                    'dateEnd',
                    $dateEnd
                );
            } else {
                $qb = $qb->andWhere(
                    $qb->expr()->gte('a.dateTime', ':dateStart')
                )->andWhere(
                    $qb->expr()->lte('a.dateTime', ':dateEnd')
                )->setParameter(
                    'dateStart',
                    $dateStart
                )->setParameter(
                    'dateEnd',
                    $dateStart + 24 * 3600
                );
            }
        }

        if ($speakerId) {
            $qb = $qb->andWhere($qb->expr()->eq('pu.id', ':speakerId'))->setParameter('speakerId', $speakerId);
        }

        if ($clubId) {
            $qb = $qb->andWhere($qb->expr()->eq('c.id', ':clubId'))->setParameter('clubId', $clubId);
        }

        return $this->getSimpleResult(
            EventSchedule::class,
            $qb->getQuery(),
            $lastValue,
            $limit,
            'date_time_2',
            'ASC'
        );
    }

    public function findEventSchedule(?User $user, string $id): ?array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(EventSchedule::class, 'es');
        $rsm->addScalarResult('is_already_subscribed', 'is_already_subscribed', Types::BOOLEAN);
        $rsm->addScalarResult('is_owned', 'is_owned', Types::BOOLEAN);
        $rsm->addScalarResult('is_subscribed', 'is_subscribed', Types::BOOLEAN);

        $nativeSQL = 'SELECT '.$rsm->generateSelectClause(['es' => 'es']).',
                        (
                            (
                                SELECT COUNT(*) FROM event_schedule_participant esp0
                                WHERE esp0.event_id = es.id
                                AND esp0.user_id NOT IN (
                                    SELECT f0.user_id FROM follow f0 WHERE f0.follower_id = :userId
                                )
                                AND esp0.user_id != :userId
                            ) = 0
                        ) AS is_already_subscribed,
                        (
                            es.owner_id = :userId
                            OR
                            EXISTS(
                                SELECT * 
                                FROM event_schedule_participant esp 
                                WHERE esp.event_id = es.id 
                                AND esp.user_id = :userId
                                AND esp.is_special_guest = false
                            )
                        ) AS is_owned,
                        EXISTS(
                            SELECT * FROM event_schedule_subscription ess
                            WHERE ess.event_schedule_id = es.id
                            AND ess.user_id = :userId 
                        ) AS is_subscribed
                      FROM event_schedule es
                      WHERE es.id = :id
                      LIMIT 1';

        return $em
                    ->createNativeQuery($nativeSQL, $rsm)
                    ->setParameter('id', $id)
                    ->setParameter('userId', $user ? $user->id : 0)
                    ->getOneOrNullResult();
    }

    public function findUpcomingEventsByIds(User $user, array $eventIds): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(EventSchedule::class, 'es');
        $rsm->addJoinedEntityFromClassMetadata(Club::class, 'eventScheduleClub', 'es', 'club');

        $rsm->addScalarResult('row', 'row', Types::INTEGER);
        $rsm->addScalarResult('is_already_subscribed', 'is_already_subscribed', Types::BOOLEAN);
        $rsm->addScalarResult('is_owned', 'is_owned', Types::BOOLEAN);
        $rsm->addScalarResult('is_subscribed', 'is_subscribed', Types::BOOLEAN);

        $nativeSQL = <<<SQL
            SELECT
                {$rsm->generateSelectClause([
                    'es' => 'es',
                    'eventScheduleClub' => 'eventScheduleClub',
                ])},
                (
                    (
                        SELECT COUNT(*) FROM event_schedule_participant esp0
                        WHERE
                            esp0.event_id = es.id
                            AND esp0.user_id NOT IN (
                               SELECT f0.user_id FROM follow f0 WHERE f0.follower_id = :userId
                            )
                            AND esp0.user_id != :userId
                    ) = 0
                ) AS is_already_subscribed,
                (
                    es.owner_id = :userId
                    OR EXISTS(
                        SELECT * 
                        FROM event_schedule_participant esp 
                        WHERE esp.event_id = es.id 
                        AND esp.user_id = :userId
                        AND esp.is_special_guest = false
                    )
                ) AS is_owned,
                EXISTS(
                    SELECT * FROM event_schedule_subscription ess
                    WHERE ess.event_schedule_id = es.id
                    AND ess.user_id = :userId 
                ) AS is_subscribed
            FROM event_schedule es
                JOIN users owner on es.owner_id = owner.id
                LEFT JOIN club eventScheduleClub ON eventScheduleClub.id = es.club_id
            WHERE
                es.id IN (:eventIds)
        SQL;

        $nativeQuery = $em->createNativeQuery(
            $nativeSQL,
            $rsm
        )
        ->setParameter('userId', $user->id, Types::INTEGER)
        ->setParameter('eventIds', $eventIds);

        return $nativeQuery->getResult();
    }

    public function findUpcomingEventSchedule(User $user, ?string $clubId, int $lastValue, int $limit = 20): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(EventSchedule::class, 'es');
        $rsm->addJoinedEntityFromClassMetadata(Club::class, 'eventScheduleClub', 'es', 'club');

        $rsm->addScalarResult('row', 'row', Types::INTEGER);
        $rsm->addScalarResult('is_already_subscribed', 'is_already_subscribed', Types::BOOLEAN);
        $rsm->addScalarResult('is_owned', 'is_owned', Types::BOOLEAN);
        $rsm->addScalarResult('is_subscribed', 'is_subscribed', Types::BOOLEAN);

        $sqlEventScheduleMutualLanguagesSnippet = SQLEventScheduleMutualLanguagesSnippet::sql();

        $additionalWhereFilterByClubId = '';
        if ($clubId && Uuid::isValid($clubId)) {
            $additionalWhereFilterByClubId = 'AND es.club_id = :clubId';
        }

        $nativeSQL = <<<SQL
            WITH
                userClubs AS (
                    SELECT club_id AS id
                    FROM club_participant
                    WHERE
                        user_id = :userId
                        AND role IN (:activeRoles)
                )
            SELECT
                {$rsm->generateSelectClause([
                    'es' => 'es',
                    'eventScheduleClub' => 'eventScheduleClub',
                ])},
                (
                    (
                        SELECT COUNT(*) FROM event_schedule_participant esp0
                        WHERE
                            esp0.event_id = es.id
                            AND esp0.user_id NOT IN (
                               SELECT f0.user_id FROM follow f0 WHERE f0.follower_id = :userId
                            )
                            AND esp0.user_id != :userId
                    ) = 0
                ) AS is_already_subscribed,
                (
                    es.owner_id = :userId
                    OR EXISTS(
                        SELECT * 
                        FROM event_schedule_participant esp 
                        WHERE esp.event_id = es.id 
                        AND esp.user_id = :userId
                        AND esp.is_special_guest = false
                    )
                ) AS is_owned,
                EXISTS(
                    SELECT * FROM event_schedule_subscription ess
                    WHERE ess.event_schedule_id = es.id
                    AND ess.user_id = :userId 
                ) AS is_subscribed
            FROM event_schedule es
                JOIN users owner on es.owner_id = owner.id
                LEFT JOIN club eventScheduleClub ON eventScheduleClub.id = es.club_id
            WHERE
                es.date_time >= :time
                AND owner.state = :verified
                {$additionalWhereFilterByClubId}
                AND 
                (
                    -- You owner or co-host
                    (
                        es.owner_id = :userId
                        OR
                        :userId IN (
                            SELECT esp.user_id FROM event_schedule_participant esp WHERE esp.event_id = es.id
                        )
                        OR 
                        EXISTS(
                            SELECT 1
                            FROM users u2
                            WHERE u2.id = :userId
                            AND u2.always_show_ongoing_upcoming_events
                        )
                    )
                    OR es.club_id IN (SELECT id FROM userClubs)
                    OR -- Or have mutual languages and following or is user recommended by default
                    (
                        es.for_members_only = false
                        AND
                        {$sqlEventScheduleMutualLanguagesSnippet}
                    )
                )
                AND NOT EXISTS(
                    SELECT 1
                    FROM video_room
                    WHERE
                        event_schedule_id = es.id
                        AND started_at IS NOT NULL
                        AND is_private = false
                )
            ORDER BY es.date_time, owner.created_at DESC
        SQL;

        $nativeQuery = $em->createNativeQuery(
            'SELECT * FROM (
                SELECT *, ROW_NUMBER() OVER () as row FROM (
                    '.$nativeSQL.'
                ) q
            ) q2
            WHERE q2.row > :lastValue
            LIMIT '.$limit,
            $rsm
        )
            ->setParameter('userId', $user->id, Types::INTEGER)
            ->setParameter('time', time() - 3600)
            ->setParameter('lastValue', $lastValue, Types::INTEGER)
            ->setParameter('verified', User::STATE_VERIFIED, Types::STRING)
            ->setParameter('activeRoles', [
                ClubParticipant::ROLE_MODERATOR,
                ClubParticipant::ROLE_MEMBER,
                ClubParticipant::ROLE_OWNER,
            ]);

        if (Uuid::isValid((string) $clubId)) {
            $nativeQuery = $nativeQuery->setParameter('clubId', $clubId);
        }

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'cnt', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery('SELECT COUNT(e) as cnt FROM ('.$nativeSQL.') e', $rsmCount);
        $nativeQueryCount->setParameters($nativeQuery->getParameters());

        return $this->getResult($nativeQuery, $nativeQueryCount);
    }

    public function findUpcomingPersonalEventSchedule(
        User $user,
        int $lastValue,
        int $limit = 20
    ): array {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(EventSchedule::class, 'es');
        $rsm->addJoinedEntityFromClassMetadata(Club::class, 'eventScheduleClub', 'es', 'club');

        $rsm->addScalarResult('row', 'row', Types::INTEGER);
        $rsm->addScalarResult('is_already_subscribed', 'is_already_subscribed', Types::BOOLEAN);
        $rsm->addScalarResult('is_owned', 'is_owned', Types::BOOLEAN);
        $rsm->addScalarResult('is_subscribed', 'is_subscribed', Types::BOOLEAN);

        $nativeSQL = <<<SQL
            SELECT
                {$rsm->generateSelectClause([
                    'es' => 'es',
                    'eventScheduleClub' => 'eventScheduleClub',
                ])},
                (
                    (
                        SELECT COUNT(*) FROM event_schedule_participant esp0
                        WHERE
                            esp0.event_id = es.id
                            AND esp0.user_id NOT IN (
                               SELECT f0.user_id FROM follow f0 WHERE f0.follower_id = :userId
                            )
                            AND esp0.user_id != :userId
                    ) = 0
                ) AS is_already_subscribed,
                (
                    es.owner_id = :userId
                    OR EXISTS(
                        SELECT * 
                        FROM event_schedule_participant esp 
                        WHERE esp.event_id = es.id 
                        AND esp.user_id = :userId
                        AND esp.is_special_guest = false
                    )
                ) AS is_owned,
                EXISTS(
                    SELECT * FROM event_schedule_subscription ess
                    WHERE ess.event_schedule_id = es.id
                    AND ess.user_id = :userId 
                ) AS is_subscribed
            FROM event_schedule es
                JOIN users owner on es.owner_id = owner.id
                LEFT JOIN club eventScheduleClub ON eventScheduleClub.id = es.club_id
            WHERE
                es.date_time >= :time
                AND owner.state = :verified
                AND 
                (
                    -- subscribed event
                    EXISTS(
                        SELECT 1
                        FROM event_schedule_subscription ess
                        WHERE ess.event_schedule_id = es.id
                        AND ess.user_id = :userId 
                    )
                    OR -- You owner or co-host or speaker or private event
                    (
                        es.owner_id = :userId
                        OR
                        :userId IN (
                            SELECT esp.user_id FROM event_schedule_participant esp WHERE esp.event_id = es.id
                        )
                    )
                )
                AND NOT EXISTS(
                    SELECT 1
                    FROM video_room
                    WHERE
                        event_schedule_id = es.id
                        AND started_at IS NOT NULL
                        AND is_private = false
                )
            ORDER BY es.date_time, owner.created_at DESC
        SQL;

        $nativeQuery = $em->createNativeQuery(
            'SELECT * FROM (
                SELECT *, ROW_NUMBER() OVER () as row FROM (
                    '.$nativeSQL.'
                ) q
            ) q2
            WHERE q2.row > :lastValue
            LIMIT '.$limit,
            $rsm
        )
            ->setParameter('userId', $user->id, Types::INTEGER)
            ->setParameter('time', time() - 3600)
            ->setParameter('lastValue', $lastValue, Types::INTEGER)
            ->setParameter('verified', User::STATE_VERIFIED, Types::STRING)
            ->setParameter('activeRoles', [
                ClubParticipant::ROLE_MODERATOR,
                ClubParticipant::ROLE_MEMBER,
                ClubParticipant::ROLE_OWNER,
            ]);

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'cnt', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery('SELECT COUNT(e) as cnt FROM ('.$nativeSQL.') e', $rsmCount);
        $nativeQueryCount->setParameters($nativeQuery->getParameters());

        return $this->getResult($nativeQuery, $nativeQueryCount);
    }

    public function findEventScheduleInterests(array $eventScheduleIds): array
    {
        $rows = $this->createQueryBuilder('es')
                    ->addSelect('ei')
                    ->addSelect('i')
                    ->join('es.interests', 'ei')
                    ->join('ei.interest', 'i')
                    ->where('es.id IN (:eventScheduleIds)')
                    ->setParameter('eventScheduleIds', $eventScheduleIds)
                    ->orderBy('i.globalSort', 'DESC')
                    ->getQuery()
                    ->getResult();

        $result = [];
        /** @var EventSchedule $row */
        foreach ($rows as $row) {
            $result[$row->id->toString()] = $row->interests->map(
                fn(EventScheduleInterest $i) => $i->interest
            )->toArray();
        }

        return $result;
    }

    public function findBySubscription(Subscription $subscription, ?int $lastValue, ?int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder('eventSchedule')
            ->leftJoin('eventSchedule.videoRoom', 'room')
            ->leftJoin('eventSchedule.participants', 'participants')
            ->leftJoin('room.meetings', 'meetings')
            ->leftJoin('meetings.participants', 'meetingParticipants')
            ->leftJoin('meetingParticipants.participant', 'meetingParticipantsUser')
            ->andWhere('eventSchedule.subscription = :subscription')
            ->setParameter('subscription', $subscription);

        return $this->getSimpleResult(
            EventSchedule::class,
            $queryBuilder->getQuery(),
            $lastValue,
            $limit,
            'date_time_2',
            'DESC'
        );
    }
}
