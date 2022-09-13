<?php

namespace App\Repository\Location;

use App\Entity\Location\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method City|null find($id, $lockMode = null, $lockVersion = null)
 * @method City|null findOneBy(array $criteria, array $orderBy = null)
 * @method City[]    findAll()
 * @method City[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return City[]
     */
    public function findSuggestions(string $part, string $countryId)
    {
        return $this->createQueryBuilder('c')
            ->join('c.country', 'c2')
            ->where('c2.id = :countryId')
            ->andWhere('LOWER(c.name) LIKE :name')
            ->setParameter('countryId', $countryId)
            ->setParameter('name', mb_strtolower($part).'%')
            ->getQuery()
            ->getResult()
        ;
    }
}
