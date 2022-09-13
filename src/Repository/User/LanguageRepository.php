<?php

namespace App\Repository\User;

use App\Entity\Interest\Interest;
use App\Entity\User;
use App\Entity\User\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Language|null find($id, $lockMode = null, $lockVersion = null)
 * @method Language|null findOneBy(array $criteria, array $orderBy = null)
 * @method Language[]    findAll()
 * @method Language[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    public function findAutofillInterestsForUser(User $user): array
    {
        if (!$user->country || !$user->country->isoCode) {
            return [];
        }

        return $this->findAutofillInterestsForRegionCode($user->country->isoCode);
    }

    public function findAutofillInterestsForRegionCode(string $isoCode): array
    {
        $sql = <<<SQL
        SELECT l.* FROM country c
        JOIN language l 
        ON '"' || :isoCode || '"' = ANY (
            ARRAY(SELECT json_array_elements(l.automatic_choose_for_region_codes))::text[]
        )
        SQL;

        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Language::class, 'l');

        return $em->createNativeQuery($sql, $rsm)->setParameter('isoCode', $isoCode)->getResult();
    }
}
