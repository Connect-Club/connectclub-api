<?php

namespace App\Repository\Follow\Query;

use App\Entity\User;
use App\Repository\Follow\Fetcher\ResultWithCursorFetcher;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class RecommendedFollowingQuery
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getResult(User $owner, ?string $lastValue, int $limit): array
    {
        $rsm = new ResultSetMappingBuilder($this->em, ResultSetMappingBuilder::COLUMN_RENAMING_NONE);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addScalarResult('type_sorting', 'type_sorting', Types::FLOAT);
        $rsm->addScalarResult('row_sorting', 'row_sorting', Types::INTEGER);

        if ($lastValue) {
            [$lastTypeSorting, $lastRowSorting, $lastUserId] = json_decode($lastValue);
        } else {
            $lastTypeSorting = null;
            $lastRowSorting = null;
            $lastUserId = null;
        }

        $state = User::STATE_VERIFIED;

        $nativeFetchingSQL = "
            SELECT
                *
            FROM (
                WITH
                    already_subscribed_user_ids AS (
                        SELECT user_id FROM follow WHERE follower_id = :userId
                    ),
                    user_interest_ids AS (
                        SELECT interest_id FROM user_interest WHERE user_id = :userId
                    ), 
                    phone_contact_recommendations AS (
                        SELECT
                            u.*,
                            1 AS type_sorting,
                            pc.sort * -1 AS row_sorting
                        FROM users u
                            INNER JOIN phone_contact_number pcn ON
                                u.phone = pcn.phone_number
                                AND pcn.phone_contact_id IN (
                                    SELECT id FROM phone_contact WHERE owner_id = :userId
                                )
                            INNER JOIN phone_contact pc on pcn.phone_contact_id = pc.id
                        WHERE
                            u.id NOT IN (SELECT user_id FROM already_subscribed_user_ids)
                            AND u.state = '{$state}'
                    ),
                    priority_recommendations AS (
                        SELECT 
                            *,
                            (u.recommended_for_following_priority * 0.01) AS type_sorting,
                            u.recommended_for_following_priority AS row_sorting
                        FROM users u
                        WHERE
                            u.recommended_for_following_priority IS NOT NULL
                            AND u.id NOT IN (SELECT user_id FROM already_subscribed_user_ids)
                            AND u.state = '{$state}'
                    ),
                    mutual_interests AS (
                        SELECT
                            *,
                            3 AS type_sorting,
                            u.id * -1 AS row_sorting
                        FROM users u
                        WHERE
                            u.id NOT IN (SELECT user_id FROM already_subscribed_user_ids)
                            AND EXISTS(
                                SELECT 1
                                FROM user_interest otherUserInterest
                                WHERE
                                    user_id = u.id
                                    AND EXISTS(
                                        SELECT 1
                                        FROM user_interest_ids
                                        WHERE interest_id = otherUserInterest.interest_id
                                    )
                            )
                            AND u.state = '{$state}'
                    ),
                    contact_of_friends AS (
                        SELECT
                            u.*,
                            2 AS type_sorting,
                            u.id * -1 AS row_sorting
                        FROM users u -- My contacts users (followed or not)
                        WHERE
                            u.id NOT IN (SELECT user_id FROM already_subscribed_user_ids)
                            AND u.phone IN (
                                SELECT phone_number
                                FROM phone_contact_number pcn
                                WHERE
                                    pcn.phone_contact_id IN (
                                        SELECT id FROM phone_contact WHERE owner_id IN (
                                            SELECT u2.id
                                            FROM users u2
                                            INNER JOIN phone_contact_number pcn ON
                                                u2.phone = pcn.phone_number
                                                AND pcn.phone_contact_id IN (
                                                    SELECT id FROM phone_contact WHERE owner_id = :userId
                                                )
                                        )
                                    )
                            )
                            AND u.state = '{$state}'
                    )
                SELECT * FROM priority_recommendations
                UNION ALL
                SELECT * FROM phone_contact_recommendations WHERE id NOT IN (
                    SELECT id FROM priority_recommendations
                )
                UNION ALL
                SELECT * FROM contact_of_friends WHERE id NOT IN (
                    SELECT id FROM priority_recommendations
                    UNION ALL
                    SELECT id FROM phone_contact_recommendations
                )
                UNION ALL
                SELECT * FROM mutual_interests WHERE id NOT IN (
                    SELECT id FROM priority_recommendations
                    UNION ALL
                    SELECT id FROM phone_contact_recommendations
                    UNION ALL
                    SELECT id FROM contact_of_friends
                )
            ) q
            WHERE
                q.id != :userId
                AND q.is_tester = false
                AND q.state = 'verified'
                AND jsonb_exists_any(
                    (SELECT _u2.languages::jsonb FROM users _u2 WHERE _u2.id = :userId),
                    ARRAY(
                        SELECT jsonb_array_elements_text(q.languages::jsonb)
                    )::text[]
                )
                AND {$this->generateOffsetWhere($lastTypeSorting, $lastRowSorting, $lastUserId)}
            ORDER BY type_sorting, row_sorting, q.id
            LIMIT :limit
        ";

        $nativeQuery = $this->em->createNativeQuery($nativeFetchingSQL, $rsm)
            ->setParameter('userId', $owner->id, Types::INTEGER)
            ->setParameter('lastRowSorting', $lastRowSorting, Types::INTEGER)
            ->setParameter('lastTypeSorting', $lastTypeSorting, Types::FLOAT)
            ->setParameter('lastUserId', $lastUserId, Types::INTEGER)
            ->setParameter('limit', $limit + 1, Types::INTEGER);

        $fetcher = new ResultWithCursorFetcher($limit, ['type_sorting', 'row_sorting']);

        return $fetcher->getResult($nativeQuery);
    }

    private function generateOffsetWhere(?string $lastTypeSorting, ?string $lastRowSorting, ?string $lastUserId): string
    {
        if ($lastTypeSorting === null || $lastRowSorting === null || $lastUserId === null) {
            return 'true';
        }

        return '(
            type_sorting > :lastTypeSorting
            OR (
                type_sorting = :lastTypeSorting
                AND (
                    row_sorting > :lastRowSorting
                    OR (
                        row_sorting = :lastRowSorting
                        AND q.id > :lastUserId
                    )
                )
            )
        )';
    }
}
