<?php

namespace App\Repository\Photo;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Photo\AbstractPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method AbstractPhoto|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractPhoto|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractPhoto[]    findAll()
 * @method AbstractPhoto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbstractPhotoRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbstractPhoto::class);
    }
}
