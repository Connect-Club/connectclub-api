<?php

namespace App\Repository\Invite;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Invite\Invite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * @method Invite|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invite|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invite[]    findAll()
 * @method Invite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InviteRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invite::class);
    }

    public function findInviteByAuthorAndPhoneNumber(User $author, PhoneNumber $phoneNumber): ?Invite
    {
        return $this->createQueryBuilder('i')
                    ->where('i.author = :author')
                    ->andWhere('i.phoneNumber = :phoneNumber')
                    ->setParameter(
                        'phoneNumber',
                        PhoneNumberUtil::getInstance()->format($phoneNumber, PhoneNumberFormat::E164)
                    )
                    ->setParameter('author', $author)
                    ->getQuery()
                    ->setFirstResult(0)
                    ->setMaxResults(1)
                    ->getOneOrNullResult();
    }

    public function findActiveInviteWithPhoneNumber(PhoneNumber $phoneNumber): ?Invite
    {
        return $this->createQueryBuilder('i')
            ->where('i.registeredUser IS NULL')
            ->andWhere('i.phoneNumber = :phoneNumber')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->setParameter('phoneNumber', PhoneNumberUtil::getInstance()->format($phoneNumber, PhoneNumberFormat::E164))
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }
}
