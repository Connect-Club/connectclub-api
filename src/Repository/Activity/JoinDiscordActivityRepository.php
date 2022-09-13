<?php

namespace App\Repository\Activity;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Activity\JoinDiscordActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method JoinDiscordActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method JoinDiscordActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method JoinDiscordActivity[] findAll()
 * @method JoinDiscordActivity[] findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class JoinDiscordActivityRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoinDiscordActivity::class);
    }
}
