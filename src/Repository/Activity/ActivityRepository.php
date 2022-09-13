<?php

namespace App\Repository\Activity;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Doctrine\CursorPaginateWalker;
use App\Entity\User;
use App\Entity\Activity\Activity;
use App\Repository\BulkInsertTrait;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRepository extends ServiceEntityRepository
{
    use BulkInsertTrait;
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /** @return Activity[] */
    public function findUnreadActivity(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function findActivity(User $user, int $lastValue, int $limit = 20): array
    {
        $query = $this
                   ->createQueryBuilder('a')
                   ->addSelect('nu')
                   ->leftJoin('a.user', 'u')
                   ->leftJoin('a.nestedUsers', 'nu')
                   ->where('u.id = :userId')
                   ->getQuery()
                   ->setParameter('userId', $user->id);

        return $this->getSimpleResult(Activity::class, $query, $lastValue, $limit, 'created_at_1', 'DESC');
    }

    public function updateActivityFeedAtMessage(User $user, Activity $activity)
    {
        $em = $this->createQueryBuilder('a')->getEntityManager();

        $query = $em->createNativeQuery(
            'UPDATE activity 
             SET read_at = :currentTime 
             WHERE user_id = :userId 
             AND created_at <= :timeReadActivity 
             AND read_at IS NULL',
            new Query\ResultSetMapping()
        );

        $query->setParameter('currentTime', time())
              ->setParameter('userId', $user->id)
              ->setParameter('timeReadActivity', $activity->createdAt)
              ->execute();
    }

    public function findCountNewActivities(User $user): int
    {
        return $this->createQueryBuilder('a')
                    ->select('COUNT(a)')
                    ->where('a.user = :user')
                    ->andWhere('a.readAt IS NULL')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->setMaxResults(1)
                    ->setFirstResult(0)
                    ->getSingleScalarResult();
    }

    public function findCountsNewActivities(array $users): array
    {
        $sql = <<<SQL
            SELECT a.user_id, COUNT(*) as cnt
            FROM activity a
            WHERE a.read_at IS NULL AND a.user_id IN (:userIds)
            GROUP BY a.user_id
        SQL;

        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addScalarResult('user_id', 'user_id', Types::INTEGER);
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);

        $result = $em->createNativeQuery($sql, $rsm)
                     ->setParameter('userIds', array_unique(array_map(fn(User $u) => (int) $u->id, $users)))
                     ->getResult();

        $formattedResult = [];
        foreach ($result as $item) {
            $formattedResult[$item['user_id']] = $item['cnt'];
        }

        return $formattedResult;
    }

    public function deleteActivitiesWithUserForUser(User $forUser, User $withUser)
    {
        $rsm = new Query\ResultSetMapping();

        $this->getEntityManager()
            ->createNativeQuery(
                'DELETE FROM activity WHERE id IN (
                    SELECT a.id FROM activity a
                    JOIN activity_user au on a.id = au.activity_id AND au.user_id = :withUserId
                    WHERE a.user_id = :forUserId
                )',
                $rsm
            )
            ->setParameter('withUserId', $withUser->id)
            ->setParameter('forUserId', $forUser->id)
            ->execute();
    }

    public function deleteActivitiesWithEventScheduleId(string $eventScheduleId)
    {
        $rsm = new Query\ResultSetMapping();

        $this->getEntityManager()
            ->createNativeQuery('DELETE FROM activity WHERE event_schedule_id = :eventScheduleId', $rsm)
            ->setParameter('eventScheduleId', $eventScheduleId)
            ->execute();
    }
}
