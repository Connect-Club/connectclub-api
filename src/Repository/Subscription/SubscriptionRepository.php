<?php

namespace App\Repository\Subscription;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Subscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method Subscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method Subscription[]    findAll()
 * @method Subscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    // /**
    //  * @return Subscription[] Returns an array of Subscription objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findMy(User $author, int $lastValue, int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->andWhere('s.author = :author')
            ->setParameter('author', $author);

        return $this->getSimpleResult(
            Subscription::class,
            $queryBuilder->getQuery(),
            $lastValue,
            $limit,
            'id_0',
            'ASC'
        );
    }

    public function findActive(User $author, string $exceptId = null): ?Subscription
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->andWhere('s.author = :author')
            ->andWhere('s.isActive = true')
            ->setParameter('author', $author);

        if ($exceptId !== null) {
            $queryBuilder->andWhere('s.id != :exceptId')
                ->setParameter('exceptId', $exceptId);
        }

        $queryBuilder->setMaxResults(1);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findSummary(Subscription $subscription): array
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm
            ->addScalarResult('total_sales_count', 'totalSalesCount')
            ->addScalarResult('total_sales_amount', 'totalSalesAmount');

        $sql = <<<SQL
            SELECT
                COUNT(*) AS total_sales_count,
                SUM(payment.amount) AS total_sales_amount
            FROM subscription_payment payment
                JOIN paid_subscription paidSubscription ON paidSubscription.id = payment.paid_subscription_id
            WHERE paidSubscription.subscription_id = :subscriptionId
        SQL;

        $summary = $this->getEntityManager()->createNativeQuery($sql, $rsm)
            ->setParameter('subscriptionId', $subscription->id)
            ->getSingleResult();

        return [
            'totalSalesCount' => $summary['totalSalesCount'],
            'totalSalesAmount' => $summary['totalSalesAmount'],
            'activeSubscriptions' => $this->findActiveSubscriptionCount($subscription),
        ];
    }

    public function findChartData(
        Subscription $subscription,
        int $fromDate,
        int $toDate,
        string $groupBy,
        string $valueField,
        string $timeZone
    ): array {
        $rsm = (new ResultSetMappingBuilder($this->getEntityManager()))
            ->addScalarResult('date', 'date')
            ->addScalarResult('value', 'value');

        $valueFunctions = [
            'sum' => 'SUM(payment.amount)',
            'quantity' => 'COUNT(*)',
        ];

        $groupFields = [
            'day' => 'day',
            'month' => 'month',
        ];

        $sql = <<<SQL
            SELECT
                $valueFunctions[$valueField] as value,
                extract(EPOCH FROM date_trunc(:groupDateFormat, to_timestamp(paid_at) AT TIME ZONE :timeZone)) as date
            FROM subscription_payment payment
                JOIN paid_subscription paidSubscription ON paidSubscription.id = payment.paid_subscription_id 
            WHERE
                payment.paid_at >= :fromDate
                AND payment.paid_at < :toDate
                AND paidSubscription.subscription_id = :subscriptionId
            GROUP BY date
            ORDER BY date
        SQL;

        $result = $this->getEntityManager()->createNativeQuery($sql, $rsm)
            ->setParameter('subscriptionId', $subscription->id)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->setParameter('timeZone', $timeZone)
            ->setParameter('groupDateFormat', $groupFields[$groupBy])
            ->getResult();

        $data = [];
        $minDate = null;
        $maxDate = null;
        foreach ($result as $item) {
            if (!isset($minDate) || $item['date'] < $minDate) {
                $minDate = $item['date'];
            }

            if (!isset($maxDate) || $item['date'] > $maxDate) {
                $maxDate = $item['date'];
            }

            $data[] = $item;
        }

        return [
            'minDate' => $minDate ?? 0,
            'maxDate' => $maxDate ?? 0,
            'values' => $data,
        ];
    }

    private function findActiveSubscriptionCount(Subscription $subscription): int
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addScalarResult('active_subscriptions', 'activeSubscriptions');

        $sql = <<<SQL
            SELECT COUNT(*) AS active_subscriptions
            FROM paid_subscription
            WHERE
                subscription_id = :subscriptionId
                AND status IN (:activeStatuses)
        SQL;

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm)
            ->setParameter('subscriptionId', $subscription->id)
            ->setParameter('activeStatuses', PaidSubscription::getActiveStatuses());

        return $query->getSingleScalarResult();
    }
}
