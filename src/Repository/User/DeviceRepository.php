<?php

namespace App\Repository\User;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\User;
use App\Entity\User\Device;
use App\Repository\BulkInsertTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method Device|null find($id, $lockMode = null, $lockVersion = null)
 * @method Device|null findOneBy(array $criteria, array $orderBy = null)
 * @method Device[]    findAll()
 * @method Device[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeviceRepository extends ServiceEntityRepository
{
    use BulkInsertTrait;
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    /** @return Device[] */
    public function findDevicesOfUserIds(array $users): array
    {
        $userIds = [];
        foreach ($users as $user) {
            if ($user instanceof User) {
                $userIds[] = $user->id;
            } else {
                $userIds[] = (int) $user;
            }
        }
        $userIds = array_unique($userIds);

        if (!$userIds) {
            return [];
        }

        return $this->createQueryBuilder('d')
                    ->addSelect('u')
                    ->join('d.user', 'u')
                    ->where('u.id IN (:userIds)')
                    ->andWhere('d.token IS NOT NULL')
                    ->setParameter('userIds', $userIds)
                    ->getQuery()
                    ->getResult();
    }

    public function findDeviceByIdOrPushToken(string $deviceId, string $pushToken): ?Device
    {
        return $this->createQueryBuilder('d')
            ->where('d.token = :token')
            ->orWhere('d.id = :deviceId')
            ->setParameter('token', $pushToken)
            ->setParameter('deviceId', $deviceId)
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDeviceForUserWithModelName(User $user, string $modelName): ?Device
    {
        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.model = :model')
            ->setParameter('user', $user)
            ->setParameter('model', $modelName)
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDevicesByTokens(array $tokens): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.token IN (:tokens)')
            ->setParameter('tokens', $tokens)
            ->getQuery()
            ->getResult();
    }
}
