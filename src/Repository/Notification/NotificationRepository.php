<?php

namespace App\Repository\Notification;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Notification\Notification;
use App\Repository\BulkInsertTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    use BulkInsertTrait;
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function setStartProcessForNotifications(array $notificationsIds)
    {
        $sql = <<<SQL
        UPDATE notification SET start_process_at = :time, status = :status WHERE id IN (:notificationsIds)
        SQL;

        $this->getEntityManager()
             ->createNativeQuery($sql, new ResultSetMapping())
             ->setParameter('time', round(microtime(true) * 1000))
             ->setParameter('status', Notification::STATUS_PROCESS)
             ->setParameter('notificationsIds', $notificationsIds)
             ->execute();
    }

    public function setProcessedForNotifications(array $notificationsIds)
    {
        $sql = <<<SQL
        UPDATE notification SET processed_at = :time, status = :status WHERE id IN (:notificationsIds)
        SQL;

        $this->getEntityManager()
            ->createNativeQuery($sql, new ResultSetMapping())
            ->setParameter('time', round(microtime(true) * 1000))
            ->setParameter('status', Notification::STATUS_PROCESSED)
            ->setParameter('notificationsIds', $notificationsIds)
            ->execute();
    }

    public function setErrorForNotifications(array $notificationsIds)
    {
        $sql = <<<SQL
        UPDATE notification SET error_at = :time, status = :status WHERE id IN (:notificationsIds)
        SQL;

        $this->getEntityManager()
            ->createNativeQuery($sql, new ResultSetMapping())
            ->setParameter('time', round(microtime(true) * 1000))
            ->setParameter('status', Notification::STATUS_ERROR_PROCESSING)
            ->setParameter('notificationsIds', $notificationsIds)
            ->execute();
    }
}
