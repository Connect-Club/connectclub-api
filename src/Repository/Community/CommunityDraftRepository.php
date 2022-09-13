<?php

namespace App\Repository\Community;

use App\Entity\Community\CommunityDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CommunityDraft|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityDraft|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityDraft[]    findAll()
 * @method CommunityDraft[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityDraft::class);
    }
}
