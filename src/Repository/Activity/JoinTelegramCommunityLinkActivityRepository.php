<?php

namespace App\Repository\Activity;

use App\Entity\Activity\JoinTelegramCommunityLinkActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method JoinTelegramCommunityLinkActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method JoinTelegramCommunityLinkActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method JoinTelegramCommunityLinkActivity[] findAll()
 * @method JoinTelegramCommunityLinkActivity[] findBy(array $criteria, array $orderBy = null, $limit = null)
 */
class JoinTelegramCommunityLinkActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoinTelegramCommunityLinkActivity::class);
    }
}
