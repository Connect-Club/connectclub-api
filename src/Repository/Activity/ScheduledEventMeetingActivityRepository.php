<?php

namespace App\Repository\Activity;

use App\Entity\Activity\ScheduledEventMeetingActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ScheduledEventMeetingActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScheduledEventMeetingActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScheduledEventMeetingActivity[]    findAll()
 * @method ScheduledEventMeetingActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class ScheduledEventMeetingActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduledEventMeetingActivity::class);
    }
}
