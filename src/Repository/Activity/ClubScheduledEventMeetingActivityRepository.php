<?php

namespace App\Repository\Activity;

use App\Entity\Activity\ClubScheduledEventMeetingActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ClubScheduledEventMeetingActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubScheduledEventMeetingActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubScheduledEventMeetingActivity[] findAll()
 * @codingStandardsIgnoreStart
 * @method ClubScheduledEventMeetingActivity[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @codingStandardsIgnoreEnd
 */
class ClubScheduledEventMeetingActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubScheduledEventMeetingActivity::class);
    }
}
