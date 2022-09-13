<?php

namespace App\Repository\Photo;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Photo\UserPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method UserPhoto|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserPhoto|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserPhoto[]    findAll()
 * @method UserPhoto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserPhotoRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPhoto::class);
    }
}
