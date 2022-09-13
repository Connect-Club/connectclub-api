<?php

namespace App\Doctrine\SQL\Snippet;

use App\Entity\Club\JoinRequest;

class ClubCountAndRoleSQLSnippet
{
    public static function sql(): string
    {
        $status = JoinRequest::STATUS_CANCELLED;

        // @codingStandardsIgnoreStart
        return <<<SQL
               (SELECT COUNT(*) FROM club_participant cp WHERE cp.club_id = c.id) AS cnt,
               (
                   CASE
                       WHEN EXISTS(SELECT 1 FROM club_participant cp2 WHERE cp2.club_id = c.id AND cp2.user_id = :userId)
                           THEN (SELECT cp2.role FROM club_participant cp2 WHERE cp2.club_id = c.id AND cp2.user_id = :userId)
                       WHEN EXISTS(SELECT 1 FROM club_join_request cjr WHERE cjr.club_id = c.id AND cjr.author_id = :userId)
                           THEN (SELECT 'join_request_' || cjr.status FROM club_join_request cjr WHERE cjr.club_id = c.id AND cjr.author_id = :userId AND cjr.status != '$status' ORDER BY cjr.created_at DESC, cjr.id DESC LIMIT 1)
                   END
               ) AS status
        SQL;
        // @codingStandardsIgnoreEnd
    }
}
