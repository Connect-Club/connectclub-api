<?php

namespace App\Repository\Activity;

use App\Entity\Activity\RegisteredAsSpeakerActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RegisteredAsSpeakerActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegisteredAsSpeakerActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegisteredAsSpeakerActivity[]    findAll()
 * @method RegisteredAsSpeakerActivity[]   findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegisteredAsSpeakerActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegisteredAsSpeakerActivity::class);
    }
}
