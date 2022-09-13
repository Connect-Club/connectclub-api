<?php

namespace App\Repository\Notification;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Notification\NotificationStatistic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NotificationStatistic|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationStatistic|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationStatistic[]    findAll()
 * @method NotificationStatistic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationStatisticRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationStatistic::class);
    }
}
