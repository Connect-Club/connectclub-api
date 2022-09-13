<?php

namespace App\Repository\Club;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Doctrine\SQL\Snippet\ClubCountAndRoleSQLSnippet;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Club|null find($id, $lockMode = null, $lockVersion = null)
 * @method Club|null findOneBy(array $criteria, array $orderBy = null)
 * @method Club[]    findAll()
 * @method Club[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClubRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    public function findClubsByIdsForUser(User $user, array $clubIds): array
    {
        $sql = ClubCountAndRoleSQLSnippet::sql();

        // @codingStandardsIgnoreStart
        $sql = <<<SQL
        SELECT c.*,
               {$sql}
        FROM club c 
        WHERE c.id IN (:clubIds)
        SQL;
        // @codingStandardsIgnoreEnd

        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Club::class, 'c');
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);
        $rsm->addScalarResult('status', 'status', Types::STRING);

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameter('clubIds', $clubIds)
                  ->setParameter('userId', $user->id)
                  ->getResult();
    }

    public function findExploredClubs(User $forUser, int $limit, ?int $lastValue, ?string $search): array
    {
        $parameters = [];

        $selectMutualInterestSQLSnippet = <<<SQL
        SELECT ui.interest_id 
        FROM user_interest ui 
        WHERE ui.user_id = :userId 
        
        INTERSECT 
        
        SELECT ci.interest_id 
        FROM club_interest ci
        WHERE ci.club_id = c.id
        SQL;

        $parameters['userId'] = $forUser->id;
        if ($search !== null) {
            $where = 'LOWER(c.title) LIKE :search';
            $parameters['search'] = '%'.mb_strtolower(trim($search)).'%';
        } else {
            $where = 'NOT EXISTS (SELECT 1 FROM club_participant cp WHERE cp.club_id = c.id AND cp.user_id = :userId)';
            //phpcs:ignore
            $where .= ' AND EXISTS ('.$selectMutualInterestSQLSnippet.')';
        }

        $clubSelectClause = ClubCountAndRoleSQLSnippet::sql();

        $sql = <<<SQL
        SELECT {$clubSelectClause}, 
               c.*
        FROM club c 
        WHERE {$where}
        ORDER BY
        (
            CASE
               WHEN EXISTS(SELECT 1 FROM club_participant cp2 WHERE cp2.club_id = c.id AND cp2.user_id = :userId)
                   THEN 1
               WHEN EXISTS(SELECT 1 FROM club_join_request cjr WHERE cjr.club_id = c.id AND cjr.author_id = :userId)
                   THEN 2
               ELSE 3
            END
        ) ASC,
        (SELECT COUNT(*) FROM ({$selectMutualInterestSQLSnippet}) q) DESC
        SQL;

        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Club::class, 'c');
        $rsm->addScalarResult('status', 'status', Types::STRING);
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);

        $paginationQuery = $this->createPaginationQuery($sql, $rsm, $lastValue, $limit);
        foreach ($parameters as $parameterName => $parameterValue) {
            $paginationQuery->setParameter($parameterName, $parameterValue);
        }

        $countQuery = $this->createCountQuery($sql, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findWithFullInformation(User $forUser, string $id): ?array
    {
        $status = JoinRequest::STATUS_CANCELLED;

        // @codingStandardsIgnoreStart
        $sql = <<<SQL
            SELECT c.*, 
                (
                    SELECT COUNT(*)
                    FROM club_participant cp
                        JOIN users u ON u.id = cp.user_id
                    WHERE
                        cp.club_id = c.id
                        AND u.state = :verified
                        AND u.deleted_at IS NULL
                ) AS cnt,
                (
                   CASE
                       WHEN EXISTS(SELECT 1 FROM club_participant cp2 WHERE cp2.club_id = c.id AND cp2.user_id = :userId)
                           THEN (SELECT cp2.role FROM club_participant cp2 WHERE cp2.club_id = c.id AND cp2.user_id = :userId)
                       WHEN EXISTS(SELECT 1 FROM club_join_request cjr WHERE cjr.club_id = c.id AND cjr.author_id = :userId)
                           THEN (SELECT 'join_request_' || cjr.status FROM club_join_request cjr WHERE cjr.club_id = c.id AND cjr.author_id = :userId AND cjr.status != '$status' ORDER BY cjr.created_at DESC, cjr.id DESC LIMIT 1)
                   END
                ) AS status
            FROM club c 
            WHERE c.id = :id
        SQL;
        // @codingStandardsIgnoreEnd

        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Club::class, 'c');
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);
        $rsm->addScalarResult('status', 'status', Types::STRING);

        $item = $em->createNativeQuery($sql, $rsm)
            ->setParameter('id', $id)
            ->setParameter('userId', $forUser->id)
            ->setParameter('verified', User::STATE_VERIFIED)
            ->getOneOrNullResult();

        if (!$item) {
            return null;
        }

        return array_values($item);
    }

    public function findRelevantClubs(User $user, int $limit = 20, ?int $lastValue = null): array
    {
        $sql = ClubCountAndRoleSQLSnippet::sql();

        $sql = <<<SQL
        SELECT * FROM (
            SELECT
                   *,
                   {$sql},
                   (
                       SELECT COUNT(*)
                       FROM (
                           SELECT ui.interest_id FROM user_interest ui WHERE ui.user_id = :userId
                           INTERSECT
                           SELECT ci.interest_id FROM club_interest ci WHERE ci.club_id = c.id
                       ) q
                   ) as mutual_interest
            FROM club c
        ) q
        WHERE q.mutual_interest > 0
        ORDER BY q.mutual_interest DESC
        SQL;

        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Club::class, 'c');
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);
        $rsm->addScalarResult('status', 'status', Types::STRING);

        $paginationQuery = $this->createPaginationQuery($sql, $rsm, $lastValue ?? 0, $limit)
            ->setParameter('userId', $user->id);
        $countQuery = $this->createCountQuery($sql, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findAllClubs(User $user, ?string $query, int $limit, int $lastValue): array
    {
        $sql = ClubCountAndRoleSQLSnippet::sql();

        $querySql = '';
        $orderBy = 'c.created_at';
        $selectRating = '';
        if (null !== $query) {
            $querySql = <<<SQL
                WHERE LOWER(c.slug) LIKE :searchQuery OR
                    LOWER(c.title) LIKE :searchQuery OR
                    LOWER(c.description) LIKE :searchQuery
                    
            SQL;

            $selectRating = <<<SQL
                ,(
                    CASE WHEN LOWER(c.slug) LIKE :searchQuery THEN 1
                        WHEN LOWER(c.title) LIKE :searchQuery THEN 2
                        WHEN LOWER(c.description) LIKE :searchQuery THEN 3
                        ELSE 0
                    END
                ) AS rating
            SQL;

            $orderBy = 'rating, c.created_at';
        }

        // @codingStandardsIgnoreStart
        $sql = <<<SQL
        SELECT c.*,
            {$sql}
            $selectRating    
        FROM club c 
        $querySql 
        ORDER BY $orderBy
        SQL;
        // @codingStandardsIgnoreEnd

        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Club::class, 'c');
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);
        $rsm->addScalarResult('status', 'status', Types::STRING);

        $paginationQuery = $this->createPaginationQuery($sql, $rsm, $lastValue, $limit)
                                ->setParameter('userId', $user->id);
        if (null !== $query) {
            $paginationQuery->setParameter('searchQuery', '%' . mb_strtolower($query) . '%');
            $paginationQuery->setParameter('orderQuery', mb_strtolower($query));
        }

        $countQuery = $this->createCountQuery($sql, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findMyClubs(User $user, int $limit, int $lastValue): array
    {
        $sql = <<<SQL
        SELECT c.*
        FROM club c
        INNER JOIN club_participant cp ON cp.club_id = c.id 
                                       AND cp.user_id = :userId
                                       AND cp.role IN (:roleOwner, :roleModerator)
        ORDER BY
             (CASE WHEN cp.role = :roleOwner THEN 1 WHEN cp.role = :roleModerator THEN 0 ELSE 0 END) DESC,
             c.created_at DESC
        SQL;

        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Club::class, 'c');

        $paginationQuery = $this->createPaginationQuery($sql, $rsm, $lastValue, $limit)
                                ->setParameter('userId', $user->id)
                                ->setParameter('roleOwner', ClubParticipant::ROLE_OWNER)
                                ->setParameter('roleModerator', ClubParticipant::ROLE_MODERATOR);
        $countQuery = $this->createCountQuery($sql, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }

    public function findMembersWithFollowingData(
        Club $club,
        User $currentUser,
        ?array $userIds,
        int $lastValue,
        int $limit
    ): array {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(User::class, 'participantUser');
        $rsm->addScalarResult('is_follower', 'isFollower', Types::BOOLEAN);
        $rsm->addScalarResult('is_following', 'isFollowing', Types::BOOLEAN);
        $rsm->addScalarResult('role', 'role', Types::STRING);

        if (null !== $userIds && 0 === count($userIds)) {
            return [[], $lastValue];
        }

        $userIdsCriteria = '';
        if (null !== $userIds) {
            $userIdsCriteria = 'AND participantUser.id IN (:userIds)';
        }

        $sql = <<<SQL
            SELECT
                {$rsm->generateSelectClause(['q' => 'q', 'participantUser' => 'participantUser'])},
                EXISTS(
                    SELECT * FROM follow WHERE follower_id = participantUser.id AND user_id = :currentUserId
                ) as is_follower,
                EXISTS(
                    SELECT * FROM follow WHERE user_id = participantUser.id AND follower_id = :currentUserId
                ) as is_following,
                role
            FROM club_participant
                JOIN users participantUser ON participantUser.id = club_participant.user_id 
            WHERE
                club_participant.club_id = :clubId
                AND participantUser.state = :stateVerified
                AND participantUser.deleted_at IS NULL
                $userIdsCriteria
            ORDER BY
                CASE WHEN role = :owner THEN 1 WHEN role = :moderator THEN 2 ELSE 3 END,
                club_participant.user_id DESC
        SQL;

        $paginationQuery = $this->createPaginationQuery($sql, $rsm, $lastValue, $limit)
            ->setParameter('currentUserId', $currentUser->id)
            ->setParameter('clubId', $club->id)
            ->setParameter('moderator', ClubParticipant::ROLE_MODERATOR)
            ->setParameter('owner', ClubParticipant::ROLE_OWNER)
            ->setParameter('stateVerified', User::STATE_VERIFIED);

        if (null !== $userIds) {
            $paginationQuery->setParameter('userIds', $userIds);
        }

        $countQuery = $this->createCountQuery($sql, $rsm, $paginationQuery);

        return $this->getResult($paginationQuery, $countQuery);
    }
}
