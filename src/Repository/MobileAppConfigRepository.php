<?php

namespace App\Repository;

use App\Entity\MobileAppConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method MobileAppConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method MobileAppConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method MobileAppConfig[]    findAll()
 * @method MobileAppConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MobileAppConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobileAppConfig::class);
    }
}
