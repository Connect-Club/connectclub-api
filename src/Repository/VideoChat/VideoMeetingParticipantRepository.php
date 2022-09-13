<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Club\ClubParticipant;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;
use Twilio\Rest\Video;

/**
 * @method VideoMeetingParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoMeetingParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoMeetingParticipant[]    findAll()
 * @method VideoMeetingParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoMeetingParticipantRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoMeetingParticipant::class);
    }

    public function findParticipantsExceptClubOwners(VideoRoom $room): array
    {
        $sql = <<<SQL
        SELECT vmp.participant_id 
        FROM video_meeting_participant vmp
        JOIN video_meeting vm on vm.id = vmp.video_meeting_id
        JOIN video_room vr on vm.video_room_id = vr.id
        JOIN event_schedule es on vr.event_schedule_id = es.id
        WHERE vr.id = :videoRoomId
        AND es.club_id IS NOT NULL
        AND vmp.participant_id NOT IN (
            SELECT cp.user_id FROM club_participant cp WHERE cp.role IN (:roles) AND cp.club_id = es.club_id
        )
        SQL;

        $em = $this->getEntityManager();
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('participant_id', 'participant_id', Types::INTEGER);

        $query = $em->createNativeQuery($sql, $rsm)
                    ->setParameter('videoRoomId', $room->id)
                    ->setParameter('roles', [ClubParticipant::ROLE_MODERATOR, ClubParticipant::ROLE_OWNER]);

        return $query->getArrayResult();
    }

    public function findOnlineUsers(VideoRoom $videoRoom): array
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(VideoMeetingParticipant::class, 'vmp');

        return $em->createNativeQuery(
            'SELECT * FROM video_meeting_participant vmp
            JOIN video_meeting vm on vm.id = vmp.video_meeting_id
            WHERE vm.video_room_id = :videoRoomId AND vmp.end_time IS NULL',
            $rsm
        )->setParameter('videoRoomId', $videoRoom->id)->getResult();
    }

    /** @return VideoMeetingParticipant[] */
    public function findOnlineParticipantsInVideoRooms(array $videoRoomNames): array
    {
        $qb = $this->createQueryBuilder('vmp');

        return $qb
                    ->addSelect('vm')
                    ->addSelect('vr')
                    ->addSelect('c')
                    ->addSelect('u')
                    ->addSelect('i')
                    ->addSelect('ia')
                    ->addSelect('vmp')
                    ->addSelect('vrc')
                    ->addSelect('ev')
                    ->addSelect('evp')
                    ->addSelect('evpu')
                    ->join('vmp.videoMeeting', 'vm')
                    ->join('vm.videoRoom', 'vr')
                    ->join('vr.config', 'vrc')
                    ->join('vr.community', 'c')
                    ->join('vmp.participant', 'u')
                    ->leftJoin('u.invite', 'i')
                    ->leftJoin('i.author', 'ia')
                    ->leftJoin('vr.eventSchedule', 'ev')
                    ->leftJoin('ev.participants', 'evp')
                    ->leftJoin('evp.user', 'evpu')
                    ->where($qb->expr()->in('c.name', $videoRoomNames))
                    ->andWhere('vmp.endTime IS NULL')
                    ->orderBy('vmp.startTime', 'ASC')
                    ->getQuery()
                    ->getResult();
    }

    public function findUserParticipantInVideoRoom(VideoRoom $videoRoom, User $user): ?VideoMeetingParticipant
    {
        return $this->createQueryBuilder('vmp')
                    ->join('vmp.videoMeeting', 'vm')
                    ->where('vm.videoRoom = :videoRoom')
                    ->andWhere('vmp.participant = :user')
                    ->getQuery()
                    ->setParameter('user', $user)
                    ->setParameter('videoRoom', $videoRoom)
                    ->setMaxResults(1)
                    ->setFirstResult(0)
                    ->getOneOrNullResult();
    }

    public function findParticipantsWithTimeInterval(VideoRoom $videoRoom, int $timeOnRoomGreatOrEqual)
    {
        $sql = <<<SQL
        SELECT * FROM (
            SELECT u.id AS user_id, MAX(vmp.end_time) - MIN(vmp.start_time) AS time_on_room, u.is_tester
            FROM video_meeting_participant vmp
            JOIN video_meeting vm on vm.id = vmp.video_meeting_id
            JOIN video_room vr on vm.video_room_id = vr.id
            JOIN community c on c.video_room_id = vr.id
            JOIN users u on vmp.participant_id = u.id
            WHERE c.name = :name 
            AND vmp.end_time IS NOT NULL            
            GROUP BY u.id
        ) q
        WHERE q.time_on_room >= :interval
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('user_id', 'user_id', Types::INTEGER);
        $rsm->addScalarResult('time_on_room', 'time_on_room', Types::INTEGER);
        $rsm->addScalarResult('is_tester', 'is_tester', Types::BOOLEAN);

        $em = $this->getEntityManager();

        return $em->createNativeQuery($sql, $rsm)
                  ->setParameters(['name' => $videoRoom->community->name, 'interval' => $timeOnRoomGreatOrEqual])
                  ->getArrayResult();
    }

    public function findSpeakersForVideoRoom(VideoRoom $videoRoom): array
    {
        $sql = <<<SQL
        SELECT * FROM (
            SELECT DISTINCT ON (u.id) u.id, u.name, u.surname, vmp.start_time
            FROM video_meeting_participant vmp
                JOIN video_meeting vm on vm.id = vmp.video_meeting_id
                JOIN video_room vr on vr.id = vm.video_room_id
                JOIN community c on vr.id = c.video_room_id
                JOIN users u on u.id = vmp.participant_id
            WHERE c.name = :name
            AND vmp.endpoint_allow_incoming_media = true
            AND vmp.end_time IS NULL
        ) q
        ORDER BY q.start_time
        SQL;

        $em = $this->getEntityManager();
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'user_id', Types::INTEGER);
        $rsm->addScalarResult('name', 'name', Types::STRING);
        $rsm->addScalarResult('surname', 'surname', Types::STRING);

        $query = $em->createNativeQuery($sql, $rsm)->setParameter('name', $videoRoom->community->name);

        return $query->getArrayResult();
    }
}
