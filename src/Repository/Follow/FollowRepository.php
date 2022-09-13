<?php

namespace App\Repository\Follow;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\ConnectClub;
use App\Entity\Club\Club;
use App\Entity\Follow\Follow;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Follow\Query\RecommendedFollowingQuery;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use App\Repository\LastValuePaginationQueryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @method Follow|null find($id, $lockMode = null, $lockVersion = null)
 * @method Follow|null findOneBy(array $criteria, array $orderBy = null)
 * @method Follow[]    findAll()
 * @method Follow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FollowRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait, HandleNativeQueryLastValuePaginationTrait, LastValuePaginationQueryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    /** @return User[]|ArrayCollection */
    public function findFollowedUsers(User $follower): ArrayCollection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('f')
                ->select('u')
                ->join('f.user', 'u')
                ->where('f.follower = :follower')
                ->setParameter('follower', $follower)
                ->getQuery()
                ->getResult()
        );
    }

    public function findFollowingForUser(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20
    ): array {
        [$paginationQuery, $countQuery] = $this->createFindFollowingsQueries(
            $currentUser,
            $owner,
            $userIds,
            $lastValue,
            $limit
        );

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findNotMutualFollowingForUser(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20
    ): array {
        [$paginationQuery, $countQuery] = $this->createFindFollowingsQueries(
            $currentUser,
            $owner,
            $userIds,
            $lastValue,
            $limit,
            '
                followers AS (
                    SELECT f.follower_id
                    FROM follow f
                    WHERE f.user_id = :userId
                )
            ',
            'AND NOT EXISTS(SELECT 1 FROM followers WHERE follower_id = f.user_id)'
        );

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findFriendsForUser(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20
    ): array {
        [$paginationQuery, $countQuery] = $this->createFindFollowersQueries(
            $currentUser,
            $owner,
            $userIds,
            $lastValue,
            $limit,
            '
                followings AS (
                    SELECT f.user_id
                    FROM follow f
                    WHERE f.follower_id = :userId
                )
            ',
            'AND EXISTS(SELECT 1 FROM followings WHERE user_id = f.follower_id)'
        );
        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findPendingFollowersForUser(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20
    ): array {
        [$paginationQuery, $countQuery] = $this->createFindFollowersQueries(
            $currentUser,
            $owner,
            $userIds,
            $lastValue,
            $limit,
            '
                followings AS (
                    SELECT f.user_id
                    FROM follow f
                    WHERE f.follower_id = :userId
                )
            ',
            'AND NOT EXISTS(SELECT 1 FROM followings WHERE user_id = f.follower_id)'
        );
        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findFollowersForUser(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20
    ): array {
        [$paginationQuery, $countQuery] = $this->createFindFollowersQueries(
            $currentUser,
            $owner,
            $userIds,
            $lastValue,
            $limit
        );

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findRecommendedFollowing(User $owner, int $lastValue, int $limit = 20): array
    {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');

        $selectClause = $rsm->generateSelectClause(['u' => 'u']);

        $nativeFetchingSQL = '(SELECT '.$selectClause.', (SELECT COUNT(*) FROM follow f2 WHERE f2.user_id = u.id) as cnt
        FROM users u
            WHERE u.recommended_for_following_priority IS NOT NULL 
            AND u.deleted_at IS NULL
            AND NOT EXISTS(SELECT * FROM follow WHERE follower_id = :userId AND user_id = u.id)
        ORDER BY u.recommended_for_following_priority)
        UNION ALL
        (SELECT '.$selectClause.', (SELECT COUNT(*) FROM follow f2 WHERE f2.user_id = u.id) as cnt
        FROM users u
        WHERE NOT EXISTS(SELECT * FROM follow WHERE follower_id = :userId AND user_id = u.id)
        AND jsonb_exists_any(
            (SELECT _u2.languages::jsonb FROM users _u2 WHERE _u2.id = :userId),
            ARRAY(
                SELECT jsonb_array_elements_text(u.languages::jsonb)
            )::text[]
        )
        AND EXISTS(
            SELECT ui1.interest_id 
            FROM user_interest ui1
            JOIN interest i0 ON i0.id = ui1.interest_id 
            WHERE ui1.user_id = :userId
            
            INTERSECT
            
            SELECT ui2.interest_id 
            FROM user_interest ui2
            JOIN interest i1 ON i1.id = ui2.interest_id 
            WHERE ui2.user_id = u.id
        )
        AND u.id != :userId
        AND u.state = \''.User::STATE_VERIFIED.'\'
        AND u.deleted_at IS NULL
        ORDER BY cnt DESC)';

        $paginationQuery = $this->createPaginationQuery($nativeFetchingSQL, $rsm, $lastValue, $limit, '')
            ->setParameter('userId', $owner->id, Types::INTEGER);

        $countQuery = $this->createCountQuery($nativeFetchingSQL, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findRecommendedFollowingByContactsAndInterests(
        User $owner,
        ?string $lastValue,
        int $limit = 20
    ): array {
        $query = new RecommendedFollowingQuery($this->getEntityManager());

        return $query->getResult($owner, $lastValue, $limit);
    }

    public function findRecommendedFollowingByContacts(User $owner, int $lastValue, int $limit = 20): array
    {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');

        $selectClause = $rsm->generateSelectClause(['u' => 'u']);

        $inviteId = $owner->invite ? $owner->invite->author->id : 0;

        $nativeFetchingSQL = '
        SELECT '.$selectClause.', (SELECT COUNT(*) FROM follow f2 WHERE f2.user_id = u.id) as cnt 
        FROM users u
        WHERE (
            u.id = '.$inviteId.'
            OR
            u.phone IN (SELECT phone_number FROM phone_contact WHERE owner_id = :userId) 
        ) 
        AND NOT EXISTS(
            SELECT * FROM follow WHERE follower_id = :userId AND user_id = u.id
        ) 
        AND u.id != :userId 
        AND u.state = \''.User::STATE_VERIFIED.'\'
        AND u.deleted_at IS NULL
        ORDER BY (
          CASE
              WHEN u.id = '.$inviteId.' THEN 0
              ELSE u.id 
          END
        ) ASC';

        $paginationQuery = $this->createPaginationQuery($nativeFetchingSQL, $rsm, $lastValue, $limit, "
            ORDER BY (
                CASE
                  WHEN q.id0 = $inviteId THEN 0
                  ELSE q.id0 
                END
            ), q.cnt DESC
        ")
            ->setParameter('userId', $owner->id, Types::INTEGER);

        $nativeQueryCount = $this->createCountQuery($nativeFetchingSQL, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $nativeQueryCount);
    }

    public function findUsersNotAlreadyFollowedByIds(User $follower, array $ids): array
    {
        $queryIFollowing = $this->createQueryBuilder('f')
            ->select('u.id')
            ->join('f.user', 'u')
            ->where('f.follower = :user');

        $qb = $this->getEntityManager()->createQueryBuilder();

        return $qb
            ->select('u2')
            ->from('App:User', 'u2')
            ->where($qb->expr()->in('u2.id', $ids))
            ->andWhere('u2.state IN (:states)')
            ->andWhere($qb->expr()->notIn('u2.id', $queryIFollowing->getDQL()))
            ->setParameter('user', $follower)
            ->setParameter('states', [User::STATE_VERIFIED, User::STATE_INVITED])
            ->getQuery()
            ->getResult();
    }

    public function findFriendsFollowers(
        User $user,
        ?VideoRoom $forPing,
        int $lastValue,
        int $limit = 20,
        ?array $userIdsFilter = null,
        ?string $ignoreClubIdMembers = null,
        bool $showPendingMembers = false
    ): array {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');

        $ignoreSQL = '';
        if ($forPing) {
            $ignoreSQL = 'AND (SELECT COUNT(*) FROM video_meeting_participant vmp
                                    JOIN video_meeting vm on vm.id = vmp.video_meeting_id
                                   WHERE vmp.participant_id = u.id
                                   AND vmp.end_time IS NULL
                                   AND vm.video_room_id = :videoRoomId) = 0';
        }

        if ($ignoreClubIdMembers && Uuid::isValid($ignoreClubIdMembers)) {
            $ignoreSQL .= ' AND NOT EXISTS(
                SELECT 1 FROM club_participant cp WHERE cp.club_id = :clubId AND cp.user_id = u.id
            ) AND NOT EXISTS(
                SELECT 1 FROM club_invite ci WHERE ci.club_id = :clubId
                                             AND ci.user_id = u.id
                                             '.($showPendingMembers ? 'AND ci.notification_send_at IS NOT NULL' : '').'
            )';
        }

        $userIdsCondition = '';
        if ($userIdsFilter !== null) {
            $userIdsCondition = 'AND f.follower_id IN(:userIdsFilter)';
        }

        $nativeFetchingSQL = <<<SQL
            WITH
                online_friends AS (
                    SELECT {$rsm->generateSelectClause(['u' => 'u'])}
                    FROM follow f
                        JOIN users u ON f.follower_id = u.id
                    WHERE
                        f.user_id = :userId
                        AND f.follower_id IN (SELECT f2.user_id FROM follow f2 WHERE f2.follower_id = :userId)
                        AND u.deleted_at IS NULL
                        AND u.state = :stateVerified
                        AND (
                            (:time - u.last_time_activity) < :onlineUserActivityLimit
                            OR u.online_in_video_room = true
                        )
                        $ignoreSQL
                        $userIdsCondition
                )
            SELECT * FROM (
                SELECT * FROM online_friends o1 ORDER BY o1.last_time_activity DESC
            ) q
            
            UNION ALL
            
            SELECT * FROM (
                SELECT {$rsm->generateSelectClause(['u' => 'u'])}
                FROM follow f
                    JOIN users u ON f.follower_id = u.id
                WHERE
                    f.user_id = :userId
                    AND f.follower_id IN (SELECT f2.user_id FROM follow f2 WHERE f2.follower_id = :userId)
                    AND u.deleted_at IS NULL
                    AND f.follower_id NOT IN (SELECT id FROM online_friends)
                    AND u.state = :stateVerified
                    $ignoreSQL
                    $userIdsCondition
                ORDER BY u.last_time_activity DESC
            ) q
        SQL;

        $paginationQuery = $this->createPaginationQuery($nativeFetchingSQL, $rsm, $lastValue, $limit, '')
            ->setParameter('userId', $user->id, Types::INTEGER)
            ->setParameter('stateVerified', User::STATE_VERIFIED)
            ->setParameter('time', time(), Types::INTEGER)
            ->setParameter('onlineUserActivityLimit', ConnectClub::ONLINE_USER_ACTIVITY_LIMIT);

        if ($userIdsCondition) {
            $paginationQuery->setParameter('userIdsFilter', $userIdsFilter);
        }

        if ($forPing) {
            $paginationQuery->setParameter('videoRoomId', $forPing->id);
        }

        if ($ignoreClubIdMembers) {
            $paginationQuery->setParameter('clubId', $ignoreClubIdMembers);
        }

        $nativeQueryCount = $this->createCountQuery($nativeFetchingSQL, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $nativeQueryCount);
    }

    public function findOnlineFriendsCount(User $user): int
    {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);

        $nativeFetchingSQL = 'SELECT COUNT(*) as cnt
                              FROM follow f
                              JOIN users u ON f.follower_id = u.id
                              WHERE f.user_id = :userId
                              AND u.state = \''.User::STATE_VERIFIED.'\'
                              AND f.follower_id IN (SELECT f2.user_id FROM follow f2 WHERE f2.follower_id = :userId)
                              AND u.deleted_at IS NULL
                              AND 
                              (
                                (:time - u.last_time_activity) < '.ConnectClub::ONLINE_USER_ACTIVITY_LIMIT.'
                                OR 
                                online_in_video_room = true
                              )';

        return (int) $em->createNativeQuery($nativeFetchingSQL, $rsm)
            ->setParameter('userId', $user->id)
            ->setParameter('time', time())
            ->getSingleScalarResult();
    }

    /** @return User[] */
    public function findFriendsByIds(User $user, array $friendIds): array
    {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');

        $nativeFetchingSQL = 'SELECT '.$rsm->generateSelectClause(['u' => 'u']).'
                              FROM follow f
                              JOIN users u ON f.follower_id = u.id
                              WHERE f.user_id = :userId
                              AND u.state = \''.User::STATE_VERIFIED.'\'
                              AND f.follower_id IN (:followerIds)
                              AND f.follower_id IN (SELECT f2.user_id FROM follow f2 WHERE f2.follower_id = :userId)
                              AND u.deleted_at IS NULL
                              ORDER BY f.created_at DESC';

        $nativeQuery = $em->createNativeQuery($nativeFetchingSQL, $rsm)
            ->setParameter('userId', $user->id, Types::INTEGER)
            ->setParameter('followerIds', $friendIds);

        return $nativeQuery->getResult();
    }

    public function findFriendById(User $user, int $userId): ?User
    {
        return $this->findFriendsByIds($user, [$userId])[0] ?? null;
    }

    /** @return User[] */
    public function findFollowers(User $user, ?Club $inClub = null, ?User\Language $language = null): array
    {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addJoinedEntityFromClassMetadata(User\Device::class, 'd', 'u', 'devices');
        $rsm->addJoinedEntityFromClassMetadata(Invite::class, 'i', 'u', 'invite');

        $additionalQuery = '';
        if ($inClub) {
            $additionalQuery = 'AND EXISTS (
                SELECT 1 FROM club_participant cp WHERE cp.user_id = u.id ANd cp.club_id = :clubId
            )';
        }

        if ($language) {
            $additionalQuery .= ' AND EXISTS (
                SELECT 1 FROM user_language ul WHERE ul.user_id = u.id AND ul.language_id = :languageId
            )';
        }

        $nativeFetchingSQL = 'SELECT '.$rsm->generateSelectClause(['u' => 'u', 'd' => 'd']).'
                              FROM follow f
                              JOIN users u ON f.follower_id = u.id
                              LEFT JOIN device d on u.id = d.user_id
                              LEFT JOIN invite i on u.id = i.registered_user_id
                              WHERE f.user_id = :userId
                              AND u.state = \''.User::STATE_VERIFIED.'\'
                              AND u.deleted_at IS NULL
                              '.$additionalQuery.'
                              ORDER BY f.created_at DESC';

        $nativeQuery = $em->createNativeQuery($nativeFetchingSQL, $rsm)
            ->setParameter('userId', $user->id, Types::INTEGER);

        if ($inClub) {
            $nativeQuery = $nativeQuery->setParameter('clubId', $inClub->id->toString());
        }

        if ($language) {
            $nativeQuery = $nativeQuery->setParameter('languageId', $language->id);
        }

        return $nativeQuery->getResult();
    }

    public function findFollowedByBetweenUsers(User $user, User $withUser, int $lastValue, int $limit = 20): array
    {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addScalarResult('is_follower', 'is_follower', Types::BOOLEAN);
        $rsm->addScalarResult('is_following', 'is_following', Types::BOOLEAN);

        $nativeFetchingSQL = '
            SELECT '.$rsm->generateSelectClause(['u' => 'u']).',
            EXISTS(
                SELECT * FROM follow f3 WHERE f3.follower_id = u.id AND f3.user_id = :currentUserId
            ) as is_follower,
            EXISTS(
                SELECT * FROM follow f2 WHERE f2.user_id = u.id AND f2.follower_id = :currentUserId
            ) as is_following
            FROM users u 
            WHERE u.id IN (
                SELECT f1.user_id FROM follow f1 WHERE f1.follower_id = :currentUserId
                INTERSECT 
                SELECT f2.follower_id FROM follow f2 WHERE f2.user_id = :secondUserId
            )
            AND u.state = \''.User::STATE_VERIFIED.'\'
        ';

        $paginationQuery = $this->createPaginationQuery(
            $nativeFetchingSQL,
            $rsm,
            $lastValue,
            $limit,
            'ORDER BY q.id DESC'
        )
            ->setParameter('currentUserId', $user->id, Types::INTEGER)
            ->setParameter('secondUserId', $withUser->id, Types::INTEGER);

        $countQuery = $this->createCountQuery($nativeFetchingSQL, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findConnectingCountForUser(User $user): int
    {
        $rsm = new Query\ResultSetMapping();
        $rsm->addScalarResult('cnt', 'cnt');

        $sql = <<<SQL
            WITH
                followers AS (
                    SELECT f.follower_id
                    FROM follow f
                    WHERE f.user_id = :userId
                )
            SELECT count(*) as cnt
            FROM follow f
                JOIN users u ON f.user_id = u.id
            WHERE
                f.follower_id = :userId
                AND u.state = :stateVerified
                AND NOT EXISTS(SELECT 1 FROM followers WHERE follower_id = f.user_id)
                AND u.deleted_at IS NULL
        SQL;

        $query = $this->getEntityManager()
            ->createNativeQuery($sql, $rsm);

        $query
            ->setParameter('userId', $user->id)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        return $query->getSingleScalarResult();
    }

    public function findFriendCountForUser(User $user): int
    {
        $rsm = new Query\ResultSetMapping();
        $rsm->addScalarResult('cnt', 'cnt');

        $sql = <<<SQL
            WITH
                followings AS (
                    SELECT f.user_id
                    FROM follow f
                    WHERE f.follower_id = :userId
                )
            SELECT count(*) as cnt
            FROM follow f
                JOIN users u ON f.follower_id = u.id
            WHERE
                f.user_id = :userId
                AND u.state = :stateVerified
                AND EXISTS(SELECT 1 FROM followings WHERE user_id = f.follower_id)
                AND u.deleted_at IS NULL
        SQL;

        $query = $this->getEntityManager()
            ->createNativeQuery($sql, $rsm);

        $query
            ->setParameter('userId', $user->id)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        return $query->getSingleScalarResult();
    }

    public function findMutualFriendCount(User $user1, User $user2): int
    {
        $rsm = new Query\ResultSetMapping();
        $rsm->addScalarResult('cnt', 'cnt');

        $sql = <<<SQL
            WITH
                user_1_friends AS (
                    SELECT f.user_id as id FROM follow f WHERE f.follower_id = :userId1
                    INTERSECT 
                    SELECT f.follower_id as id FROM follow f WHERE f.user_id = :userId1
                ),
                user_2_friends AS (
                    SELECT f.user_id as id FROM follow f WHERE f.follower_id = :userId2
                    INTERSECT 
                    SELECT f.follower_id as id FROM follow f WHERE f.user_id = :userId2
                )
            SELECT count(*) as cnt
            FROM (
                 SELECT id FROM user_1_friends
                 INTERSECT
                 SELECT id FROM user_2_friends
            ) userIds
                JOIN users u ON u.id = userIds.id
            WHERE
                u.state = :stateVerified
                AND u.deleted_at IS NULL
        SQL;

        $query = $this->getEntityManager()
            ->createNativeQuery($sql, $rsm);

        $query
            ->setParameter('userId1', $user1->id)
            ->setParameter('userId2', $user2->id)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        return $query->getSingleScalarResult();
    }

    public function findMutualFriends(User $currentUser, User $targetUser, int $lastValue, int $limit = 20): array
    {
        $rsm = new Query\ResultSetMappingBuilder(
            $this->getEntityManager(),
            Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT
        );
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');

        $sql = <<<SQL
            WITH
                user_1_friends AS (
                    SELECT f.user_id as id FROM follow f WHERE f.follower_id = :userId
                    INTERSECT 
                    SELECT f.follower_id as id FROM follow f WHERE f.user_id = :userId
                ),
                user_2_friends AS (
                    SELECT f.user_id as id FROM follow f WHERE f.follower_id = :currentUserId
                    INTERSECT 
                    SELECT f.follower_id as id FROM follow f WHERE f.user_id = :currentUserId
                )
            SELECT
                {$rsm->generateSelectClause(['q' => 'q', 'u' => 'u'])}
            FROM (
                 SELECT id FROM user_1_friends
                 INTERSECT
                 SELECT id FROM user_2_friends
            ) userIds
                JOIN users u ON u.id = userIds.id
            WHERE
                u.state = :stateVerified
                AND u.deleted_at IS NULL
        SQL;

        $paginationQuery = $this->createPaginationQuery($sql, $rsm, $lastValue, $limit, '')
            ->setParameter('userId', $targetUser->id, Types::INTEGER)
            ->setParameter('currentUserId', $currentUser->id, Types::INTEGER)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        $countQuery = $this->createCountQuery($sql, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    private function createPaginationQuery(
        string $nativeFetchingSQL,
        Query\ResultSetMapping $rsm,
        int $lastValue,
        int $limit,
        string $over = 'ORDER BY q.cnt DESC'
    ): NativeQuery {
        $em = $this->getEntityManager();

        $rsm->addScalarResult('row', 'row', Types::INTEGER);

        return $em->createNativeQuery("
            SELECT *
            FROM (
                SELECT
                    *,
                    ROW_NUMBER() OVER ($over) as row
                FROM (
                    $nativeFetchingSQL
                ) q
            ) q2
            WHERE q2.row > :lastValue
            LIMIT :limit
        ", $rsm)
            ->setParameter('limit', $limit)
            ->setParameter('lastValue', $lastValue, Types::INTEGER);
    }

    private function createCountQuery(
        string $nativeFetchingSQL,
        Query\ResultSetMapping $rsm,
        NativeQuery $paginationQuery
    ): NativeQuery {
        $em = $this->getEntityManager();

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'e', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery("SELECT COUNT(e) as cnt FROM ($nativeFetchingSQL) e", $rsmCount);
        $nativeQueryCount->setParameters($paginationQuery->getParameters());

        return $nativeQueryCount;
    }

    /**
     * @return NativeQuery[]
     */
    private function createFindFollowingsQueries(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20,
        string $with = '',
        string $additionalConditions = ''
    ): array {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(Follow::class, 'f');
        $rsm->addJoinedEntityFromClassMetadata(User::class, 'u', 'f', 'follower');
        $rsm->addScalarResult('is_follower', 'is_follower', Types::BOOLEAN);
        $rsm->addScalarResult('is_following', 'is_following', Types::BOOLEAN);

        $with = $with ? "WITH $with" : '';

        $userIdsCondition = '';
        if (null !== $userIds) {
            $userIdsCondition = 'AND f.user_id IN (:userIds)';
        }

        $nativeFetchingSQL = <<<SQL
            $with
            SELECT
                {$rsm->generateSelectClause(['q' => 'q', 'u' => 'u'])},
                (SELECT COUNT(*) FROM follow f2 WHERE f2.user_id = f.follower_id) as cnt,
                EXISTS(
                    SELECT * FROM follow f3 WHERE f3.follower_id = u.id AND f3.user_id = :currentUserId
                ) as is_follower,
                EXISTS(
                    SELECT * FROM follow f2 WHERE f2.user_id = u.id AND f2.follower_id = :currentUserId
                ) as is_following
            FROM follow f
                JOIN users u ON f.user_id = u.id
            WHERE
                f.follower_id = :userId
                $additionalConditions
                $userIdsCondition
                AND u.state = :stateVerified
                AND u.deleted_at IS NULL
            ORDER BY f.user_id DESC
        SQL;

        $paginationQuery = $this->createPaginationQuery($nativeFetchingSQL, $rsm, $lastValue, $limit)
            ->setParameter('userId', $owner->id, Types::INTEGER)
            ->setParameter('currentUserId', $currentUser->id, Types::INTEGER)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        if ($userIdsCondition) {
            $paginationQuery->setParameter('userIds', $userIds);
        }

        $countQuery = $this->createCountQuery($nativeFetchingSQL, $rsm, $paginationQuery);

        return [$paginationQuery, $countQuery];
    }

    /**
     * @return NativeQuery[]
     */
    private function createFindFollowersQueries(
        User $currentUser,
        User $owner,
        ?array $userIds,
        int $lastValue,
        int $limit = 20,
        string $with = '',
        string $additionalConditions = ''
    ): array {
        $em = $this->getEntityManager();

        $rsm = new Query\ResultSetMappingBuilder($em, Query\ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(Follow::class, 'f');
        $rsm->addJoinedEntityFromClassMetadata(User::class, 'u', 'f', 'follower');
        $rsm->addScalarResult('is_follower', 'is_follower', Types::BOOLEAN);
        $rsm->addScalarResult('is_following', 'is_following', Types::BOOLEAN);

        $with = $with ? "WITH $with" : '';

        $userIdsCondition = '';
        if (null !== $userIds) {
            $userIdsCondition = 'AND f.follower_id IN (:userIds)';
        }

        $nativeFetchingSQL = <<<SQL
            {$with}
            SELECT
                {$rsm->generateSelectClause(['q' => 'q', 'u' => 'u'])},
                (SELECT COUNT(*) FROM follow f2 WHERE f2.user_id = f.follower_id) as cnt,
                EXISTS(
                    SELECT * FROM follow f3 WHERE f3.follower_id = u.id AND f3.user_id = :currentUserId
                ) as is_follower,
                EXISTS(
                    SELECT * FROM follow f2 WHERE f2.user_id = u.id AND f2.follower_id = :currentUserId
                ) as is_following
            FROM follow f
                JOIN users u ON f.follower_id = u.id
            WHERE
                f.user_id = :userId
                {$additionalConditions}
                {$userIdsCondition}
                AND u.state = :stateVerified
                AND u.deleted_at IS NULL
            ORDER BY f.created_at DESC
        SQL;

        $paginationQuery = $this->createPaginationQuery($nativeFetchingSQL, $rsm, $lastValue, $limit)
            ->setParameter('userId', $owner->id, Types::INTEGER)
            ->setParameter('currentUserId', $currentUser->id, Types::INTEGER)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        if ($userIdsCondition) {
            $paginationQuery->setParameter('userIds', $userIds);
        }

        $countQuery = $this->createCountQuery($nativeFetchingSQL, $rsm, $paginationQuery);

        return [$paginationQuery, $countQuery];
    }
}
