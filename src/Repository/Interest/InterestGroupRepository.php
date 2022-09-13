<?php

namespace App\Repository\Interest;

use App\Entity\Interest\InterestGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method InterestGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method InterestGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method InterestGroup[]    findAll()
 * @method InterestGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InterestGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterestGroup::class);
    }
}
