<?php

namespace App\Repository\VideoChat\Object;

use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoRoomSpeakerLocationObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoRoomSpeakerLocationObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoRoomSpeakerLocationObject[]    findAll()
 */
class VideoRoomSpeakerLocationObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoRoomSpeakerLocationObject::class);
    }
}
