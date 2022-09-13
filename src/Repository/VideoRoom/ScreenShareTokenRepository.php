<?php

namespace App\Repository\VideoRoom;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoRoom\ScreenShareToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ScreenShareToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScreenShareToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScreenShareToken[]    findAll()
 * @method ScreenShareToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScreenShareTokenRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScreenShareToken::class);
    }
}
