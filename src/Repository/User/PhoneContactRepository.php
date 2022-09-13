<?php

namespace App\Repository\User;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\User\PhoneContactNumber;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Entity\User\PhoneContact;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * @method PhoneContact|null find($id, $lockMode = null, $lockVersion = null)
 * @method PhoneContact|null findOneBy(array $criteria, array $orderBy = null)
 * @method PhoneContact[]    findAll()
 * @method PhoneContact[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhoneContactRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhoneContact::class);
    }

    public function findPendingPhoneContacts(User $owner, int $lastValue, int $limit = 20): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Invite::class, 'i');
        $rsm->addScalarResult('row', 'row', Types::INTEGER);
        $rsm->addScalarResult('count', 'count', Types::INTEGER);

        $sql = 'SELECT 
                    i.*,
                    (
                       SELECT COUNT(*)
                       FROM phone_contact pc2
                       WHERE pc2.owner_id != :userId
                       AND pc2.phone_number = i.phone_number
                   ) AS count
                FROM invite i
                WHERE i.author_id = :userId 
                AND i.registered_user_id IS NULL
                AND NOT EXISTS (
                    SELECT * 
                    FROM invite i1
                    WHERE i1.registered_user_id IS NOT NULL 
                    AND i1.phone_number = i.phone_number
                )
                ORDER BY i.created_at DESC';

        $nativeQuery = $em->createNativeQuery(
            'SELECT * FROM (
                SELECT *, ROW_NUMBER() OVER () as row FROM (
                    '.$sql.'
                ) q
            ) q2
            WHERE q2.row > :lastValue
            LIMIT '.$limit,
            $rsm
        )
        ->setParameter('userId', $owner->id, Types::INTEGER)
        ->setParameter('lastValue', $lastValue, Types::INTEGER);

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'e', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery('SELECT COUNT(e) as cnt FROM ('.$sql.') e', $rsmCount);
        $nativeQueryCount->setParameters($nativeQuery->getParameters());

        return $this->getResult($nativeQuery, $nativeQueryCount);
    }

    public function findCountPendingPhoneContacts(User $user): int
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);

        $sql = 'SELECT COUNT(*) AS cnt
                FROM invite i
                WHERE i.author_id = :userId 
                AND i.registered_user_id IS NULL
                AND NOT EXISTS (
                    SELECT * 
                    FROM invite i1
                    WHERE i1.registered_user_id IS NOT NULL 
                    AND i1.phone_number = i.phone_number
                )';

        return (int) $em->createNativeQuery($sql, $rsm)->setParameter('userId', $user->id)->getSingleScalarResult();
    }

    public function findContactsByUserId(User $user, array $phoneContactIds): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(PhoneContact::class, 'pc');
        $rsm->addJoinedEntityFromClassMetadata(PhoneContactNumber::class, 'pcn', 'pc', 'phoneNumbers');

        $rsm->addScalarResult('count', 'count', Types::INTEGER);
        $rsm->addScalarResult('is_invited', 'is_invited', Types::BOOLEAN);
        $rsm->addScalarResult('is_pending', 'is_pending', Types::BOOLEAN);

        $selectClause = $rsm->generateSelectClause(['pc' => 'pc', 'pcn' => 'pcn']);

        $sql = 'SELECT
                       '.$selectClause.',
                       (
                           SELECT COUNT(*) 
                           FROM phone_contact pc2 
                           WHERE pc2.owner_id != :userId 
                           AND pc2.phone_number = pc.phone_number
                       ) AS count,
                       NOT EXISTS(
                           SELECT pcn.phone_number
                           FROM phone_contact_number pcn
                           WHERE pcn.phone_contact_id = pc.id
                
                           EXCEPT
                           
                           SELECT u.phone
                           FROM users u
                           WHERE u.phone IN (
                               SELECT pcn.phone_number
                               FROM phone_contact_number pcn
                               WHERE pcn.phone_contact_id = pc.id
                           )
                           AND u.state = \''.User::STATE_VERIFIED.'\'
                       ) AS is_invited,
                       NOT EXISTS(
                           SELECT pcn.phone_number
                           FROM phone_contact_number pcn
                           WHERE pcn.phone_contact_id = pc.id
                
                           EXCEPT
                           
                           SELECT i.phone_number
                           FROM invite i
                           WHERE i.author_id = pc.owner_id
                       ) AS is_pending
                FROM phone_contact pc
                JOIN phone_contact_number pcn on pc.id = pcn.phone_contact_id
                WHERE pc.id IN (:phoneContactsIds)';

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameter('phoneContactsIds', $phoneContactIds)
                  ->setParameter('userId', $user->id)
                  ->getResult();
    }

    public function findContactPhoneNumbers(
        User $owner,
        ?string $searchPhoneNumber,
        ?string $search,
        int $lastValue,
        int $limit = 20
    ): array {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata(PhoneContact::class, 'pc');

        $rsm->addScalarResult('count', 'count', Types::INTEGER);
        $rsm->addScalarResult('is_invited', 'is_invited', Types::BOOLEAN);
        $rsm->addScalarResult('is_pending', 'is_pending', Types::BOOLEAN);
        $rsm->addScalarResult('row', 'row', Types::INTEGER);

        $selectClause = $rsm->generateSelectClause(['pc' => 'pc']);

        $additionalQueryParameters = [];

        $additionalWhere = null;
        if ($search) {
            $additionalWhere = 'LOWER(pc.full_name) LIKE :searchName';
            $additionalWhere .= ' OR LOWER(pc.full_name) LIKE :searchSurname';

            $additionalQueryParameters['searchName'] = mb_strtolower($search).'%';
            $additionalQueryParameters['searchSurname'] = '% '.mb_strtolower($search).'%';

            $phoneNumberPatternSearch = trim(preg_replace('/[^0-9+]/', '', $search));
            if ($phoneNumberPatternSearch) {
                $additionalWhere .= ' OR pc.phone_number LIKE :searchPhone';
                $additionalQueryParameters['searchPhone'] = '%'.$phoneNumberPatternSearch.'%';
            }
        }

        if ($searchPhoneNumber) {
            $additionalWhere = $additionalWhere.' OR pc.phone_number = :phoneNumber';
            $additionalQueryParameters['phoneNumber'] = $searchPhoneNumber;
        }

        $sql = 'SELECT
                       '.$selectClause.',
                       (
                           SELECT COUNT(*) 
                           FROM phone_contact pc2 
                           WHERE pc2.owner_id != :userId 
                           AND pc2.phone_number = pc.phone_number
                       ) AS count,
                       NOT EXISTS(
                           SELECT pcn.phone_number
                           FROM phone_contact_number pcn
                           WHERE pcn.phone_contact_id = pc.id
                
                           EXCEPT
                           
                           SELECT u.phone
                           FROM users u
                           WHERE u.phone IN (
                               SELECT pcn.phone_number
                               FROM phone_contact_number pcn
                               WHERE pcn.phone_contact_id = pc.id
                           )
                           AND u.state = \''.User::STATE_VERIFIED.'\'
                       ) AS is_invited,
                       NOT EXISTS(
                           SELECT pcn.phone_number
                           FROM phone_contact_number pcn
                           WHERE pcn.phone_contact_id = pc.id
                
                           EXCEPT
                           
                           SELECT i.phone_number
                           FROM invite i
                           WHERE i.author_id = pc.owner_id
                       ) AS is_pending
                FROM phone_contact pc
                WHERE pc.owner_id = :userId '.($additionalWhere ? 'AND ('.$additionalWhere.')' : '').'
                ORDER BY pc.full_name ASC';

        $nativeQuery = $em->createNativeQuery(
            'SELECT * FROM (
                SELECT *, ROW_NUMBER() OVER () as row FROM (
                    '.$sql.'
                ) q
            ) q2
            WHERE q2.row > :lastValue
            LIMIT '.$limit,
            $rsm
        )
        ->setParameter('userId', $owner->id, Types::INTEGER)
        ->setParameter('lastValue', $lastValue, Types::INTEGER);

        foreach ($additionalQueryParameters as $parameterName => $additionalQueryParameter) {
            $nativeQuery->setParameter($parameterName, $additionalQueryParameter);
        }

        $rsmCount = clone $rsm;
        $rsmCount->addScalarResult('cnt', 'e', Types::INTEGER);
        $nativeQueryCount = $em->createNativeQuery(
            'SELECT COUNT(pc) as cnt 
             FROM phone_contact pc 
             WHERE pc.owner_id = :userId '.($additionalWhere ? 'AND ('.$additionalWhere.')' : ''),
            $rsmCount
        );
        $nativeQueryCount->setParameters($nativeQuery->getParameters());

        return $this->getResult($nativeQuery, $nativeQueryCount);
    }

    /** @return User[] */
    public function findContactOwnersWithPhoneNumber(PhoneNumber $phoneNumber): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');

        $selectFirst = $rsm->generateSelectClause(['u' => 'u']);
        $selectFirst = str_replace('u.languages AS languages', 'u.languages::TEXT AS languages', $selectFirst);
        $selectFirst = str_replace('u.badges AS badges', 'u.badges::TEXT AS badges', $selectFirst);

        $selectSecond = $rsm->generateSelectClause(['u' => 'u2']);
        $selectSecond = str_replace('u2.languages AS languages', 'u2.languages::text AS languages', $selectSecond);
        $selectSecond = str_replace('u2.badges AS badges', 'u2.badges::TEXT AS badges', $selectSecond);

        $sql = 'SELECT '.$selectFirst.'
                FROM phone_contact pc
                JOIN users u on u.id = pc.owner_id
                WHERE pc.phone_number = :phoneNumber
                AND u.phone != :phoneNumber
                AND u.state = \''.User::STATE_VERIFIED.'\'
                
                UNION DISTINCT 
                
                SELECT '.$selectSecond.'
                FROM invite i
                JOIN users u2 on u2.id = i.author_id
                WHERE i.phone_number = :phoneNumber
                AND u2.state = \''.User::STATE_VERIFIED.'\'';

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameter(
                      'phoneNumber',
                      PhoneNumberUtil::getInstance()->format($phoneNumber, PhoneNumberFormat::E164)
                  )
                  ->getResult();
    }

    public function findPhoneContactsData(User $user): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id', Types::STRING);
        $rsm->addScalarResult('phone_number', 'phone_number', Types::STRING);
        $rsm->addScalarResult('full_name', 'full_name', Types::STRING);

        $sql = 'SELECT pc.id, full_name, pcn.phone_number FROM phone_contact pc
                JOIN phone_contact_number pcn on pc.id = pcn.phone_contact_id
                WHERE pc.owner_id = :ownerId';

        $em = $this->getEntityManager();

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameter('ownerId', $user->id)
                  ->getResult();
    }

    public function findRegisteredContactsWhenContainsPhoneNumber(string $phoneNumber): array
    {
        $sql = <<<SQL
        SELECT ph.owner_id, u.id AS user_id 
        FROM phone_contact ph
        JOIN users u on ph.phone_number = u.phone
        WHERE ph.owner_id IN (
            SELECT _ph.owner_id FROM phone_contact _ph WHERE _ph.phone_number = :phoneNumberNewUser
        )
        GROUP BY ph.owner_id, u.id
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('owner_id', 'owner_id', Types::INTEGER);
        $rsm->addScalarResult('user_id', 'user_id', Types::INTEGER);

        $em = $this->getEntityManager();

        $items = $em->createNativeQuery($sql, $rsm)
                    ->setParameter('phoneNumberNewUser', $phoneNumber)
                    ->getArrayResult();

        $result = [];
        foreach ($items as $item) {
            $result[$item['owner_id']] ??= [];
            $result[$item['owner_id']][] = $item['user_id'];
        }

        return $result;
    }

    public function findUserIdsByPhoneNumbers(array $phoneNumbers): array
    {
        $sql = <<<SQL
        SELECT DISTINCT u.id FROM users u WHERE u.phone IN (:phoneNumbers)
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id', Types::INTEGER);

        $em = $this->getEntityManager();

        return array_values(
            array_map(
                fn(array $row) => $row['id'],
                $em->createNativeQuery($sql, $rsm)->setParameter('phoneNumbers', $phoneNumbers)->getArrayResult()
            )
        );
    }
}
