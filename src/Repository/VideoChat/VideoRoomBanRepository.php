<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomBan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomBan|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomBan|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomBan[]    findAll()
 * @method VideoRoomBan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRoomBanRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomBan::class);
    }

    public function findBan(User $abuser, VideoRoom $videoRoom): ?VideoRoomBan
    {
        return $this->findOneBy(['abuser' => $abuser, 'videoRoom' => $videoRoom]);
    }
}
