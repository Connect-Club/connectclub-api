<?php

namespace App\Repository\Event;

use App\Entity\Event\EventScheduleParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EventScheduleParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventScheduleParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventScheduleParticipant[]    findAll()
 * @method EventScheduleParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventScheduleParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventScheduleParticipant::class);
    }

    public function findFestivalSpeakers(?string $festivalCode = null): array
    {
        $qb = $this->createQueryBuilder('esp')
            ->addSelect('u')
            ->addSelect('i')
            ->addSelect('a')
            ->join('esp.event', 'es')
            ->join('es.festivalScene', 'fs')
            ->join('esp.user', 'u')
            ->leftJoin('u.invite', 'i')
            ->leftJoin('u.avatar', 'a')
            ->orderBy('u.createdAt', 'DESC');

        if ($festivalCode !== null) {
            $qb = $qb->andWhere('es.festivalCode = :festivalCode')->setParameter('festivalCode', $festivalCode);
        }

        return $qb->getQuery()->getResult();
    }
}
