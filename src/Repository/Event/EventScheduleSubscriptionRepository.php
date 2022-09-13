<?php

namespace App\Repository\Event;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Doctrine\ConnectionSpecificResult;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Ramsey\Uuid\Doctrine\UuidType;

/**
 * @method EventScheduleSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventScheduleSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventScheduleSubscription[]    findAll()
 * @method EventScheduleSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventScheduleSubscriptionRepository extends ServiceEntityRepository
{
    const MODE_HOURLY = 3600;
    const MODE_DAILY = self::MODE_HOURLY * 24;

    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventScheduleSubscription::class);
    }

    public function deleteSubscriptionForEvent(EventSchedule $eventSchedule)
    {
        return $this->getEntityManager()
                    ->createNativeQuery(
                        'DELETE FROM event_schedule_subscription WHERE event_schedule_id = :id',
                        new ResultSetMapping()
                    )
                    ->setParameter('id', $eventSchedule->id->toString())
                    ->execute()
            ;
    }

    public function markSubscriptionsAsHandled(array $subscriptionIds)
    {
        $this->createQueryBuilder('ess')
             ->update(EventScheduleSubscription::class, 'ess')
             ->set('ess.notificationSendAt', time())
             ->where('ess.id IN (:ids)')
             ->setParameter('ids', array_unique($subscriptionIds))
             ->getQuery()
             ->execute();
    }

    /** @return EventScheduleSubscription[] */
    public function findSubscriptionOnEvent(EventSchedule $eventSchedule, bool $ignoreAlreadyHandled = false): array
    {
        $query = $this->createQueryBuilder('es')
                    ->addSelect('u')
                    ->join('es.eventSchedule', 'e')
                    ->join('es.user', 'u')
                    ->where('es.eventSchedule = :eventSchedule');

        if ($ignoreAlreadyHandled) {
            $query = $query->andWhere('es.notificationSendAt IS NULL');
        }

        return $query
                    ->getQuery()
                    ->setParameter('eventSchedule', $eventSchedule)
                    ->getResult();
    }

    public function findEventScheduleSubscriptions(EntityManagerInterface $em, int $mode): ConnectionSpecificResult
    {
        switch ($mode) {
            case self::MODE_HOURLY:
                $where = 'ess.notificationHourlySendAt IS NULL';
                $createdAtLimit = 3599; //59 minutes 59 seconds
                break;
            case self::MODE_DAILY:
                $where = 'ess.notificationDailySendAt IS NULL';
                $createdAtLimit = 23 * 3600;
                break;
            default:
                throw new InvalidArgumentException('Expected HOURLY or DAILY constants');
        }


        $rows = $this->createQueryBuilder('ess')
                     ->join('ess.eventSchedule', 'es')
                     ->join('ess.user', 'u')
                     ->addSelect('u')
                     ->addSelect('es')
                     ->where('es.dateTime >= :time')
                     ->andWhere('es.dateTime - :time <= :mode')
                     ->andWhere('es.dateTime - ess.createdAt >= :timeLimit')
                     ->andWhere($where)
                     ->setParameter('mode', $mode)
                     ->setParameter('time', time())
                     ->setParameter('timeLimit', $createdAtLimit)
                     ->getQuery()
                     ->getResult();

        return new ConnectionSpecificResult($em, $rows);
    }

    public function updateSubscriptions(EntityManagerInterface $entityManager, array $ids, int $mode)
    {
        if (!$ids) {
            return;
        }

        $entityManager
            ->createQueryBuilder()
            ->update(EventScheduleSubscription::class, 'es')
            ->set(
                $mode == self::MODE_DAILY ? 'es.notificationDailySendAt' : 'es.notificationHourlySendAt',
                round(microtime(true) * 1000)
            )
            ->where('es.id IN (:ids)')
            ->getQuery()
            ->setParameter('ids', $ids)
            ->execute();
    }
}
