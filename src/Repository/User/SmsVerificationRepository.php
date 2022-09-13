<?php

namespace App\Repository\User;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\User\SmsVerification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SmsVerification|null find($id, $lockMode = null, $lockVersion = null)
 * @method SmsVerification|null findOneBy(array $criteria, array $orderBy = null)
 * @method SmsVerification[]    findAll()
 * @method SmsVerification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SmsVerificationRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsVerification::class);
    }

    public function findRatingProvidersForPhoneNumber(string $phoneNumber, ?string $phoneIsoCode = null): array
    {
        $sql = <<<SQL
        SELECT provider_code,
               SUM(CASE WHEN cancelled_at IS NOT NULL THEN -2 WHEN authorized_at IS NULL THEN 1 ELSE -1 END) AS rating
        FROM sms_verification
        WHERE (phone_country_iso_code = :isoCode OR phone_number = :phoneNumber)
        AND created_at >= :period
        GROUP BY 1
        ORDER BY 2 DESC;
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('provider_code', 'provider_code', Types::STRING);
        $rsm->addScalarResult('rating', 'rating', Types::INTEGER);

        return $this->getEntityManager()
                    ->createNativeQuery($sql, $rsm)
                    ->setParameter('phoneNumber', $phoneNumber)
                    ->setParameter('period', strtotime('-2 weeks'))
                    ->setParameter('isoCode', $phoneIsoCode)
                    ->getArrayResult();
    }

    /** @return SmsVerification[] */
    public function findLastSmsVerifications(string $phoneNumber, int $limit = 3): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.phoneNumber = :phoneNumber')
            ->setParameter('phoneNumber', $phoneNumber)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->setMaxResults($limit)
            ->getResult();
    }

    /** @return SmsVerification[] */
    public function findLastSmsVerificationsForProvider(
        string $phoneNumber,
        string $providerCode,
        int $limit = 3
    ): array {
        return $this->createQueryBuilder('s')
                    ->where('s.phoneNumber = :phoneNumber')
                    ->andWhere('s.providerCode = :providerCode')
                    ->setParameter('providerCode', $providerCode)
                    ->setParameter('phoneNumber', $phoneNumber)
                    ->orderBy('s.createdAt', 'DESC')
                    ->getQuery()
                    ->setMaxResults($limit)
                    ->getResult();
    }

    public function findSmsVerification(string $phoneNumber): ?SmsVerification
    {
        return $this->findOneBy(['phoneNumber' => $phoneNumber], ['createdAt' => 'DESC']);
    }

    /** @return SmsVerification[] */
    public function findSmsVerificationsForIpLastDay(string $ip): array
    {
        return $this->createQueryBuilder('s')
                    ->where('s.ip = :ip')
                    ->andWhere('s.createdAt >= :startedAt')
                    ->andWhere('s.createdAt <= :endedAt')
                    ->orderBy('s.createdAt', 'DESC')
                    ->setParameter('ip', $ip)
                    ->setParameter('startedAt', strtotime(date('d.m.Y', time()).' 00:00:00'))
                    ->setParameter('endedAt', strtotime(date('d.m.Y', time()).' 23:59:59'))
                    ->getQuery()
                    ->getResult();
    }
}
