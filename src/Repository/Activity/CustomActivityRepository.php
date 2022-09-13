<?php

namespace App\Repository\Activity;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Doctrine\CursorPaginateWalker;
use App\Entity\Activity\CustomActivity;
use App\Entity\User;
use App\Entity\Activity\Activity;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CustomActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method CustomActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method CustomActivity[]    findAll()
 * @method CustomActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CustomActivityRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomActivity::class);
    }
}
