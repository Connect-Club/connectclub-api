<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Doctrine\SQL\Snippet\SQLEventScheduleMutualLanguagesSnippet;
use App\Entity\Activity\Activity;
use App\Entity\Club\Club;
use App\Entity\Community\Community;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomConfig;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoom|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoom|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoom[]    findAll()
 * @method VideoRoom[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoom::class);
    }

    public function findAlwaysReopenRooms(?User $specificOwner = null, ?int $lastValue = null, ?int $limit = 20): array
    {
        $query = $this
            ->createQueryBuilder('vr')
            ->join('vr.community', 'c')
            ->where('vr.alwaysReopen = true');

        if ($specificOwner) {
            $query = $query->andWhere('c.owner = :specificOwner')->setParameter('specificOwner', $specificOwner);
        }

        return $this->getSimpleResult(VideoRoom::class, $query->getQuery(), $lastValue, $limit, 'created_at_3', 'DESC');
    }

    public function fetchEventOnlineStatisticPerInterval(string $name, int $interval, string $mode): array
    {
        $additionalWhere = '';
        switch ($mode) {
            case 'speaker':
                $additionalWhere = 'AND vmp.endpoint_allow_incoming_media = true';
                break;
            case 'listener':
                $additionalWhere = 'AND vmp.endpoint_allow_incoming_media = false';
        }

        $sql = <<<SQL
        SELECT floor(vmp.start_time / :interval) * :interval AS interval,
               COUNT(DISTINCT vmp.participant_id) AS count
        FROM video_meeting_participant vmp
        WHERE vmp.video_meeting_id  IN (
            SELECT vr.id FROM video_room vr
            JOIN community c on vr.id = c.video_room_id
            WHERE c.name = :eventName
        )
        $additionalWhere
        GROUP BY 1
        ORDER BY 1
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('interval', 'interval', Types::INTEGER);
        $rsm->addScalarResult('count', 'count', Types::INTEGER);

        return $this->getEntityManager()
                    ->createNativeQuery($sql, $rsm)
                    ->setParameter('interval', $interval)
                    ->setParameter('eventName', $name)
                    ->getArrayResult();
    }

    public function findOneByName(string $name) : ?VideoRoom
    {
        $name = mb_strtolower($name);

        return $this->createQueryBuilder('v')
            ->select('v,c,b,cm')
            ->leftJoin('v.config', 'c')
            ->leftJoin('c.backgroundRoom', 'b')
            ->leftJoin('v.community', 'cm')
            ->where('cm.name = :name')
            ->setMaxResults(1)
            ->setFirstResult(0)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ArrayCollection|VideoRoom[]
     */
    public function findVideoRoomsByPeriod(int $startTime, int $endTime): ArrayCollection
    {
        $items = $this->createQueryBuilder('v')
            ->where('v.createdAt >= :startTime')
            ->andWhere('v.createdAt <= :endTime')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getResult();

        return new ArrayCollection($items);
    }

    public function findOnlineVideoRoom(User $user, int $lastValue, int $limit = 20, array $ignoreIds = []): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(VideoRoom::class, 'vr');
        $rsm->addJoinedEntityFromClassMetadata(Community::class, 'c', 'vr', 'community');
        $rsm->addJoinedEntityFromClassMetadata(User::class, 'u', 'c', 'owner');
        $rsm->addJoinedEntityFromClassMetadata(EventSchedule::class, 'es', 'vr', 'eventSchedule');
        $rsm->addJoinedEntityFromClassMetadata(Club::class, 'cl', 'es', 'club');
        $rsm->addJoinedEntityFromClassMetadata(VideoRoomConfig::class, 'vrc', 'vr', 'config');
        $rsm->addJoinedEntityFromClassMetadata(User\Language::class, 'l', 'vr', 'language');
        $rsm->addJoinedEntityFromClassMetadata(User\Language::class, 'lu', 'u', 'nativeLanguages');
        $rsm->addScalarResult('row', 'row', Types::INTEGER);
        $rsm->addScalarResult('count_online_participants', 'count_online_participants', Types::INTEGER);

        $rsm->addMetaResult('c', 'owner_id', 'owner_id');
        $rsm->addFieldResult('vr', 'videoRoomId', 'id');
        $rsm->addFieldResult('vr', 'is_private', 'isPrivate');
        $rsm->addFieldResult('vr', 'created_at', 'createdAt');
        $rsm->addFieldResult('vr', 'always_reopen', 'alwaysReopen');

        $languageSQLSnippet = SQLEventScheduleMutualLanguagesSnippet::sql();

        $type = VideoRoom::TYPE_NEW;

        $nativeSQL = <<<SQL
        SELECT * FROM
        (
            SELECT {$rsm->generateSelectClause(['vr' => 'vr', 'c' => 'c'])},
            (
                SELECT COUNT(*) FROM video_meeting_participant vmp WHERE vmp.video_meeting_id IN (
                    SELECT vm.id FROM video_meeting vm 
                    WHERE vm.video_room_id = vr.id AND vm.end_time IS NULL
                ) AND vmp.end_time IS NULL
            ) AS count_online_participants,
            (
                CASE
                    WHEN c.owner_id = :userId THEN 0
                    WHEN vr.always_online THEN 1
                    WHEN vr.is_private THEN 2
                    WHEN vr.always_reopen THEN 3
                    ELSE 4
                END
            ) AS sort
            FROM video_room vr
            JOIN community c on vr.id = c.video_room_id
            JOIN users u ON u.id = c.owner_id
            LEFT JOIN video_room_config vrc ON vrc.id = vr.config_id
            LEFT JOIN event_schedule es ON es.id = vr.event_schedule_id
            LEFT JOIN club cl ON es.club_id = cl.id
            LEFT JOIN language l ON l.id = vr.language_id
            LEFT JOIN user_language ul ON ul.user_id = u.id
            LEFT JOIN language lu ON ul.language_id = lu.id
            WHERE vr.type = '{$type}'
            AND (
                (vr.done_at IS NULL AND vr.started_at IS NOT NULL)
                OR
                vr.always_online
            )
            AND NOT EXISTS(
                SELECT 1 FROM user_block ub WHERE
                (
                    (ub.author_id = :userId AND ub.blocked_user_id = u.id)
                    OR
                    (ub.author_id = u.id AND ub.blocked_user_id = :userId)
                ) 
                AND ub.deleted_at IS NULL
            )
            AND 
            (
                vr.is_private = false
                OR 
                EXISTS(
                    SELECT * 
                    FROM video_room_user vru 
                    WHERE vru.video_room_id = vr.id 
                    AND vru.user_id = :userId
                )
            )
            AND 
            (
                (
                    c.owner_id = :userId 
                    OR 
                    u.is_host
                    OR
                    u.recommended_for_following_priority IS NOT NULL
                    OR
                    vr.always_reopen
                    OR
                    vr.always_online
                    OR
                    vr.is_reception
                    OR
                    -- speaker on event
                    EXISTS(
                        SELECT 1 
                        FROM event_schedule_participant esp 
                        WHERE esp.event_id = es.id 
                        AND esp.user_id = :userId
                    )
                    OR
                    -- Super user
                    EXISTS(
                        SELECT 1
                        FROM users u2
                        WHERE u2.id = :userId
                        AND u2.always_show_ongoing_upcoming_events
                    )
                    -- Club participant
                    OR
                    EXISTS(
                        SELECT 1 
                        FROM club_participant cp 
                        WHERE cp.club_id = es.club_id 
                        AND cp.user_id = :userId
                    )
                )
                OR (
                    -- Check languages and club participate
                    -- Or private
                    (
                        (
                            vr.event_schedule_id IS NOT NULL 
                            AND 
                            {$languageSQLSnippet}
                        )
                        OR
                        (
                            vr.event_schedule_id IS NULL 
                            AND
                            vr.language_id IS NOT NULL
                            AND 
                            EXISTS (
                                SELECT 1 
                                FROM user_language ul 
                                WHERE ul.language_id = vr.language_id 
                                AND ul.user_id = :userId
                            )
                        )
                        OR
                        (
                            vr.event_schedule_id IS NULL
                            AND
                            vr.language_id IS NULL
                            AND
                            jsonb_exists_any(
                                (SELECT u.languages FROM users u WHERE u.id = c.owner_id)::jsonb,
                                ARRAY(
                                   SELECT jsonb_array_elements_text(u.languages::jsonb) 
                                   FROM users u WHERE u.id = :userId
                                )::text[]
                            )
                        )
                    )
                    AND
                    -- User follows
                    EXISTS(SELECT * FROM follow f WHERE f.follower_id = :userId AND f.user_id = c.owner_id)
                )
            )
        ) q
        ORDER BY q.sort, q.created_at DESC
        SQL;


        $nativeQuery = $em->createNativeQuery(
            'SELECT * FROM (
                SELECT *, DENSE_RANK() OVER (ORDER BY q.sort, q.videoRoomId DESC) as row FROM (
                    '.$nativeSQL.'
                ) q
            ) q2
            WHERE q2.row > :lastValue AND q2.row <= :sum '.($ignoreIds ? 'AND q2.videoRoomId NOT IN (:ignore)' : ''),
            $rsm
        )
        ->setParameter('userId', $user->id, Types::INTEGER)
        ->setParameter('ignore', $ignoreIds)
        ->setParameter('sum', $limit + $lastValue)
        ->setParameter('lastValue', $lastValue, Types::INTEGER);

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'e', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery(
            'SELECT COUNT(e) as cnt FROM ('.$nativeSQL.') e '.
            ($ignoreIds ? 'WHERE e.videoRoomId NOT IN (:ignore)' : ''),
            $rsmCount
        );
        $nativeQueryCount->setParameters($nativeQuery->getParameters());

        return $this->getResult($nativeQuery, $nativeQueryCount);
    }

    /** @return VideoRoom[] */
    public function findOnlineRoomsWithoutModerators(): array
    {
        $sql = 'SELECT vr.* FROM video_room vr
                JOIN community c on vr.id = c.video_room_id
                WHERE vr.done_at IS NULL
                AND vr.started_at IS NOT NULL
                AND vr.always_reopen = false
                AND vr.always_online = false
                AND NOT EXISTS(
                    SELECT * FROM community_participant cp WHERE cp.community_id = c.id AND cp.user_id IN (
                        SELECT vmp.participant_id FROM video_meeting_participant vmp WHERE vmp.video_meeting_id IN (
                            SELECT vm.id FROM video_meeting vm WHERE vm.video_room_id = vr.id
                        ) AND (
                            (:currentTime - vmp.end_time) < 120
                            OR
                            vmp.end_time IS NULL
                        )
                    ) AND cp.role IN (
                        \''.CommunityParticipant::ROLE_MODERATOR.'\',
                        \''.CommunityParticipant::ROLE_ADMIN.'\'
                    )
                )
                AND EXISTS (
                    SELECT 1 FROM video_meeting_participant vmp WHERE vmp.video_meeting_id IN (
                        SELECT vm.id FROM video_meeting vm WHERE vm.video_room_id = vr.id
                    )
                )';

        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_NONE);
        $rsm->addRootEntityFromClassMetadata(VideoRoom::class, 'vr');

        return $em->createNativeQuery($sql, $rsm)->setParameter('currentTime', time())->getResult();
    }

    public function refresh(VideoRoom $videoRoom): VideoRoom
    {
        $this->_em->refresh($videoRoom);

        return $videoRoom;
    }
}
