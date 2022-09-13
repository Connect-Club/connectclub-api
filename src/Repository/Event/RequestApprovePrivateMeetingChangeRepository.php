<?php

namespace App\Repository\Event;

use App\Entity\Event\RequestApprovePrivateMeetingChange;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @method RequestApprovePrivateMeetingChange|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestApprovePrivateMeetingChange|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestApprovePrivateMeetingChange[]    findAll()
 */
class RequestApprovePrivateMeetingChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequestApprovePrivateMeetingChange::class);
    }

    public function findNeedApproveStatusForEventSchedules(User $user, array $ids)
    {
        $ids = array_unique(
            array_filter(
                $ids,
                fn($id) => $id && Uuid::isValid($id)
            )
        );

        return $this->createQueryBuilder('r')
            ->join('r.eventSchedule', 'es')
            ->where('r.user = :user')
            ->andWhere('es.id IN (:ids)')
            ->andWhere('r.reviewed = false')
            ->setParameter('user', $user)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(RequestApprovePrivateMeetingChange $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(RequestApprovePrivateMeetingChange $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }
}
