<?php

namespace App\Repository;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Chat\AbstractChat;
use App\Entity\Chat\Chat;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Interest\Interest;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Util\ConnectClub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findVerifiedUsers(?int $lastValue, int $limit = 100): array
    {
        return $this->getSimpleResult(
            User::class,
            $this->createQueryBuilder('u')
                 ->addSelect('i')
                 ->join('u.invite', 'i')
                 ->where('u.state = :state')
                 ->setParameter('state', User::STATE_VERIFIED)
                 ->getQuery(),
            $lastValue,
            $limit,
            'id_0',
            'DESC'
        );
    }

    /**
     * @return User|null
     */
    public function findUserByAppleSubOrEmail(string $appleSub, ?string $email)
    {
        $builder = $this->createQueryBuilder('u')
            ->where('u.appleProfile.id = :appleProfileId');

        if ($email) {
            $builder->orWhere('u.email = :email')
                ->setParameter('email', $email);
        }

        return $builder->setParameter('appleProfileId', $appleSub)
            ->setMaxResults(1)
            ->setFirstResult(0)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return User[] */
    public function findUsersByIds(array $userIds): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();
    }

    public function findUserByPhoneNumber(PhoneNumber $phoneNumber): ?User
    {
        return $this->findOneBy(['phone' => $phoneNumber]);
    }

    public function findUserByUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->setFirstResult(0)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUsersByIdsWithFollowingData(
        ?User $forUser,
        array $userIds,
        bool $selectClubData = false,
        bool $withBlockedUsers = false
    ): array {
        $em = $this->getEntityManager();

        $additionalJoins = [];

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addJoinedEntityFromClassMetadata(Invite::class, 'i', 'u', 'invite');
        $rsm->addJoinedEntityFromClassMetadata(User::class, 'a', 'i', 'author');
        $rsm->addJoinedEntityFromClassMetadata(Club::class, 'inviteClub', 'i', 'club');
        $rsm->addJoinedEntityFromClassMetadata(Interest::class, 'i2', 'u', 'interests');
        if ($selectClubData) {
            $rsm->addJoinedEntityFromClassMetadata(ClubParticipant::class, 'clubParticipant', 'u', 'clubParticipants');
            $rsm->addJoinedEntityFromClassMetadata(Club::class, 'participantClub', 'clubParticipant', 'club');
            $additionalJoins[] = 'LEFT JOIN club_participant clubParticipant ON clubParticipant.user_id = u.id';
            $additionalJoins[] = 'LEFT JOIN club participantClub ON participantClub.id = clubParticipant.club_id';
        }

        $rsm->addScalarResult('is_follower', 'is_follower', Types::BOOLEAN);
        $rsm->addScalarResult('is_following', 'is_following', Types::BOOLEAN);
        $rsm->addScalarResult('count_followers', 'count_followers', Types::INTEGER);
        $rsm->addScalarResult('count_following', 'count_following', Types::INTEGER);
        $rsm->addScalarResult('is_blocked', 'is_blocked', Types::BOOLEAN);

        $additionalJoins = implode("\n", $additionalJoins);

        $additionalWhere = '';
        if (!$withBlockedUsers) {
            $additionalWhere = 'AND NOT EXISTS(
                SELECT 1 FROM user_block ub WHERE
                                            (
                                                (ub.author_id = :userId AND ub.blocked_user_id = u.id)
                                                OR
                                                (ub.author_id = u.id AND ub.blocked_user_id = :userId)
                                            ) 
                                            AND ub.deleted_at IS NULL
            )';
        }

        $nativeSQL = <<<SQL
            SELECT 
                {$rsm->generateSelectClause(['u' => 'u', 'i' => 'i', 'i2' => 'i2', 'a' => 'a'])},
                EXISTS(
                    SELECT * FROM follow f WHERE f.follower_id = u.id AND f.user_id = :userId
                ) as is_follower,
                EXISTS(
                    SELECT * FROM follow f WHERE f.user_id = u.id AND f.follower_id = :userId
                ) as is_following,
                (
                    SELECT COUNT(*)
                    FROM follow f
                        JOIN users follower ON follower.id = f.follower_id AND follower.state = :stateVerified
                    WHERE f.user_id = u.id
                ) as count_followers,
                (
                    SELECT COUNT(*)
                    FROM follow f
                        JOIN users following ON following.id = f.user_id AND following.state = :stateVerified
                    WHERE f.follower_id = u.id
                ) as count_following,
                EXISTS(
                    SELECT 1 FROM user_block ub WHERE ub.author_id = :userId 
                                                AND ub.blocked_user_id = u.id 
                                                AND ub.deleted_at IS NULL
                ) AS is_blocked
            FROM users u
                LEFT JOIN user_interest ui ON u.id = ui.user_id
                LEFT JOIN interest i2 ON ui.interest_id = i2.id
                LEFT JOIN invite i ON i.registered_user_id = u.id
                LEFT JOIN users a ON a.id = i.author_id
                LEFT JOIN club inviteClub ON inviteClub.id = i.club_id
                $additionalJoins
            WHERE
                u.id IN (:userIds)
                AND u.deleted_at IS NULL
                $additionalWhere
        SQL;

        return array_map(
            'array_values',
            $em->createNativeQuery($nativeSQL, $rsm)
                ->setParameter('userId', $forUser->id ?? 0)
                ->setParameter('userIds', $userIds)
                ->setParameter('stateVerified', User::STATE_VERIFIED)
                ->getResult()
        );
    }

    public function findMostPopularUsersByInvites(): array
    {
        $sql = 'SELECT u.*, COUNT(i) AS cnt
                FROM users u
                INNER JOIN invite i ON u.id = i.author_id AND i.registered_user_id IS NOT NULL
                WHERE u.is_tester = false
                AND u.state = \''.User::STATE_VERIFIED.'\'
                GROUP BY u.id
                ORDER BY cnt DESC
                LIMIT 100';

        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addScalarResult('cnt', 'count', Types::INTEGER);

        $result = [];
        foreach ($em->createNativeQuery($sql, $rsm)->getResult() as $item) {
            $result[] = [
                'user' => $item[0],
                'count' => $item['count'],
            ];
        }

        return $result;
    }

    public function findUserWithClubs(): array
    {
        $sql = <<<SQL
        SELECT u.id, c.slug
        FROM users u
        LEFT JOIN club_participant cp on u.id = cp.user_id
        LEFT JOIN club c on cp.club_id = c.id
        WHERE u.state = 'verified'
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id', Types::INTEGER);
        $rsm->addScalarResult('slug', 'slug', Types::STRING);

        $queryResult = $this->getEntityManager()->createNativeQuery($sql, $rsm)->getArrayResult();

        $result = [];
        foreach ($queryResult as $row) {
            $result[$row['id']] ??= [];
            $result[$row['id']][] = $row['slug'];
        }

        array_map('array_unique', $result);

        return $result;
    }
}
