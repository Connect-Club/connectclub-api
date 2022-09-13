<?php

namespace App\Repository\Club;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Club\Club;
use App\Entity\Club\ClubInvite;
use App\Entity\Club\ClubParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ClubInvite|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubInvite|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubInvite[]    findAll()
 * @method ClubInvite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClubInviteRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubInvite::class);
    }

    public function findClubInvites(User $byUser, string $clubId, array $userIds): array
    {
        $sql = <<<SQL
        SELECT ci.user_id
        FROM club_invite ci
        WHERE ci.club_id = :clubId
        AND ci.user_id IN (:userIds)
        AND EXISTS (
            SELECT 1 
            FROM club_participant cp 
            WHERE cp.club_id = :clubId 
              AND cp.user_id = :byUserId 
              AND cp.role IN (:roles)
        )
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('user_id', 'user_id');

        return array_map(
            fn(array $row) => $row['user_id'],
            $this->getEntityManager()
                ->createNativeQuery($sql, $rsm)
                ->setParameter('clubId', $clubId)
                ->setParameter('byUserId', $byUser->id)
                ->setParameter('userIds', $userIds)
                ->setParameter('roles', [ClubParticipant::ROLE_MODERATOR, ClubParticipant::ROLE_OWNER])
                ->getArrayResult()
        );
    }

    public function createTokenForMyNetwork(Club $club, User $user)
    {
        $sql = <<<SQL
        INSERT INTO club_invite (id, club_id, user_id, created_by_id, created_at)
        SELECT uuid_generate_v4(), :clubId, f.user_id, :userId, :time
        FROM follow f
        JOIN users u ON f.user_id = u.id
        JOIN follow f2 ON f2.user_id = :userId AND f2.follower_id = f.user_id
        WHERE f.follower_id = :userId
        AND u.state = :verified
        AND NOT EXISTS(
            SELECT 1 FROM club_participant cp WHERE cp.club_id = :clubId AND cp.user_id = f.user_id
        ) AND NOT EXISTS(
            SELECT 1 FROM club_invite ci WHERE ci.club_id = :clubId AND ci.user_id = f.user_id
        )
        LIMIT :clubInvitesLimit
        ON CONFLICT DO NOTHING
        SQL;

        $em = $this->getEntityManager();

        $em->createNativeQuery('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"', new ResultSetMapping())->execute();

        $em->createNativeQuery(
            $sql,
            new ResultSetMapping()
        )->setParameters([
            'clubId' => $club->id->toString(),
            'userId' => $user->id,
            'time' => time(),
            'clubInvitesLimit' => $club->freeInvites,
            'verified' => User::STATE_VERIFIED,
        ])->execute();
    }
}
