<?php

namespace App\Repository\VideoChat;

use App\Entity\VideoChat\VideoRoomDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomDraft|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomDraft|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomDraft[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomDraft::class);
    }

    /** @return VideoRoomDraft[] */
    public function findAll() : array
    {
        return $this->createQueryBuilder('d')
            ->select('d, b')
            ->leftJoin('d.backgroundRoom', 'b')
            ->getQuery()
            ->getResult();
    }
}
