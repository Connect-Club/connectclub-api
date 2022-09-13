<?php

namespace App\Repository\Activity;

use App\Entity\Activity\NewUserFromWaitingListActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * @method NewUserFromWaitingListActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewUserFromWaitingListActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewUserFromWaitingListActivity[]    findAll()
 * @method NewUserFromWaitingListActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class NewUserFromWaitingListActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewUserFromWaitingListActivity::class);
    }

    public function removeActivityWithPhoneNumber(PhoneNumber $phoneNumber)
    {
        $this->createQueryBuilder('a')
             ->delete()
             ->where('a.phoneNumber = :phoneNumber')
             ->setParameter(
                 'phoneNumber',
                 PhoneNumberUtil::getInstance()->format($phoneNumber, PhoneNumberFormat::E164)
             )
             ->getQuery()
             ->execute();
    }
}
