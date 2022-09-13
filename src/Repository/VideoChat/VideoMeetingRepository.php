<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method VideoMeeting|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoMeeting|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoMeeting[]    findAll()
 * @method VideoMeeting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoMeetingRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoMeeting::class);
    }

    public function findMeetingWithOnlineUsers(VideoRoom $videoRoom): ?VideoMeeting
    {
        $items = $this->createQueryBuilder('m')
            ->addSelect('p')
            ->leftJoin('m.participants', 'p')
            ->leftJoin('p.participant', 'u')
            ->where('p.endTime IS NULL')
            ->andWhere('m.videoRoom = :videoRoom')
            ->andWhere('m.endTime IS NULL')
            ->orderBy('m.id', 'DESC')
            ->setParameter('videoRoom', $videoRoom)
            ->getQuery()
            ->getResult()
        ;

        return $items[0] ?? null;
    }

    /** @return VideoMeeting[] */
    public function findActiveMeetingsWithOnlineUsers(array $videoRoomIds): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('p')
            ->addSelect('u')
            ->addSelect('v')
            ->join('m.participants', 'p')
            ->join('p.participant', 'u')
            ->join('m.videoRoom', 'v')
            ->where('p.endTime IS NULL')
            ->andWhere('v.id IN (:videoRoomIds)')
            ->orderBy('m.id', 'DESC')
            ->setParameter('videoRoomIds', $videoRoomIds)
            ->getQuery()
            ->getResult();
    }

    /** @return VideoMeeting[]|ArrayCollection */
    public function findActiveMeetings(): ArrayCollection
    {
        $items = $this->createQueryBuilder('m')
            ->where('m.endTime IS NULL')
            ->getQuery()
            ->getResult();

        return new ArrayCollection($items);
    }

    public function findMeetingsInVideoRoom(VideoRoom $videoRoom): array
    {
        return $this->createQueryBuilder('vm')
                    ->addSelect('vmp')
                    ->addSelect('u')
                    ->join('vm.participants', 'vmp')
                    ->join('vmp.participant', 'u')
                    ->where('vm.videoRoom = :videoRoom')
                    ->setParameter('videoRoom', $videoRoom)
                    ->getQuery()
                    ->getResult();
    }
}
