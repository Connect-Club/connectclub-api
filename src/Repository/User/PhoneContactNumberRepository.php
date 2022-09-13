<?php

namespace App\Repository\User;

use App\Entity\User\PhoneContactNumber;
use App\Entity\User;
use App\Entity\User\PhoneContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidType;

/**
 * @method PhoneContactNumber|null find($id, $lockMode = null, $lockVersion = null)
 * @method PhoneContactNumber|null findOneBy(array $criteria, array $orderBy = null)
 * @method PhoneContactNumber[]    findAll()
 * @method PhoneContactNumber[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhoneContactNumberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhoneContactNumber::class);
    }

    public function findAllPhoneNumbersDataForUser(array $phoneContactsIds = []): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('phone_number', 'phone_number', Types::STRING);
        $rsm->addScalarResult('phone_contact_id', 'phone_contact_id', Types::STRING);
        $rsm->addScalarResult('is_invited', 'is_invited', Types::BOOLEAN);
        $rsm->addScalarResult('is_pending', 'is_pending', Types::BOOLEAN);

        $sql = 'SELECT
                pcn.phone_number,
                pcn.phone_contact_id,
                EXISTS(
                   SELECT *
                   FROM users u
                   WHERE u.phone = pcn.phone_number
                   AND u.state IN (:states)
                ) AS is_invited,
                EXISTS(
                   SELECT *
                   FROM invite i
                   WHERE i.author_id = pc.owner_id
                   AND i.phone_number = pcn.phone_number
                ) AS is_pending
            FROM phone_contact_number pcn
            JOIN phone_contact pc ON pc.id = pcn.phone_contact_id
            WHERE pc.id IN (:phoneContactsIds)';

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameter('phoneContactsIds', $phoneContactsIds)
                  ->setParameter('states', [User::STATE_VERIFIED, User::STATE_INVITED])
                  ->getArrayResult();
    }

    public function findAllPhoneNumbersData(User $user, array $phoneNumbers): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('phone_number', 'phone_number', Types::STRING);
        $rsm->addScalarResult('is_invited', 'is_invited', Types::BOOLEAN);
        $rsm->addScalarResult('is_pending', 'is_pending', Types::BOOLEAN);

        $sql = 'SELECT
                pcn.phone_number,
                EXISTS(
                   SELECT *
                   FROM users u
                   WHERE u.phone = pcn.phone_number
                   AND u.state = \''.User::STATE_VERIFIED.'\'
                ) AS is_invited,
                EXISTS(
                   SELECT *
                   FROM invite i
                   WHERE i.author_id = :ownerId
                   AND i.phone_number = pcn.phone_number
                ) AS is_pending
            FROM phone_contact_number pcn
            JOIN phone_contact pc on pc.id = pcn.phone_contact_id
            WHERE pc.owner_id = :ownerId
            AND pcn.phone_number IN (:phoneNumbers)';

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameter('phoneNumbers', $phoneNumbers)
                  ->setParameter('ownerId', $user->id)
                  ->getArrayResult();
    }

    public function findAllPhoneNumbersForContactIds(array $phoneContactsIds = []): array
    {
        $rows = $this->createQueryBuilder('pcn')
                    ->select('pcn.phoneNumber')
                    ->addSelect('pc.id')
                    ->join('pcn.phoneContact', 'pc')
                    ->where('pc.id IN (:phoneContactsIds)')
                    ->setParameter('phoneContactsIds', $phoneContactsIds)
                    ->getQuery()
                    ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $result[$row['id']->toString()] ??= [];
            $result[$row['id']->toString()][] = $row['phoneNumber'];
        }

        return $result;
    }

    public function findPhoneNumberContacts(User $user, array $phoneNumbers): array
    {
        return $this->createQueryBuilder('pcn')
                    ->addSelect('pc')
                    ->join('pcn.phoneContact', 'pc')
                    ->where('pc.owner = :owner')
                    ->andWhere('pcn.phoneNumber IN (:phoneNumbers)')
                    ->setParameter('owner', $user)
                    ->setParameter('phoneNumbers', $phoneNumbers)
                    ->getQuery()
                    ->getResult();
    }
}
