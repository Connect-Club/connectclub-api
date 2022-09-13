<?php

namespace App\Repository\Subscription;

use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PaidSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaidSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaidSubscription[]    findAll()
 * @method PaidSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaidSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaidSubscription::class);
    }

    public function findActive(Subscription $subscription, User $subscriber): ?PaidSubscription
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('paidSubscription')
            ->from(PaidSubscription::class, 'paidSubscription')
            ->join('paidSubscription.subscriber', 'subscriber')
            ->join('paidSubscription.subscription', 'subscription')
            ->where('subscriber = :subscriber')
            ->andWhere('subscription = :subscription')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in('paidSubscription.status', PaidSubscription::getActiveStatuses()),
                    $qb->expr()->andX(
                        $qb->expr()->eq('paidSubscription.status', PaidSubscription::STATUS_INCOMPLETE),
                        $qb->expr()->gt('paidSubscription.waitingForPaymentConfirmationUpTo', time())
                    )
                )
            )
            ->setParameter('subscription', $subscription)
            ->setParameter('subscriber', $subscriber);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findForUser(Subscription $subscription, User $subscriber): ?PaidSubscription
    {
        return $this->findOneBy([
            'subscription' => $subscription,
            'subscriber' => $subscriber,
        ]);
    }

    // /**
    //  * @return PaidSubscription[] Returns an array of PaidSubscription objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PaidSubscription
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
