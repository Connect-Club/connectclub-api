<?php

namespace App\Repository\Photo;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Photo\NftImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NftImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method NftImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method NftImage[]    findAll()
 * @method NftImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NftImageRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NftImage::class);
    }
}
