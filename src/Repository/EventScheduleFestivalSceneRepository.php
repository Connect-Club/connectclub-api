<?php

namespace App\Repository;

use App\Entity\EventScheduleFestivalScene;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EventScheduleFestivalScene|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventScheduleFestivalScene|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventScheduleFestivalScene[]    findAll()
 * @method EventScheduleFestivalScene[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventScheduleFestivalSceneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventScheduleFestivalScene::class);
    }
}
