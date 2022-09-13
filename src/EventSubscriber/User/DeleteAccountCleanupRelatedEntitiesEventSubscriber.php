<?php

namespace App\EventSubscriber\User;

use App\Entity\User;
use App\Event\User\BanAccountEvent;
use App\Event\User\DeleteAccountEvent;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Service\MatchingClient;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DeleteAccountCleanupRelatedEntitiesEventSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $bus;
    private MatchingClient $matchingClient;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        MatchingClient $matchingClient
    ) {
        $this->entityManager = $entityManager;
        $this->bus = $bus;
        $this->matchingClient = $matchingClient;
    }

    public function onDeleteAccount(DeleteAccountEvent $deleteAccountEvent)
    {
        $user = $deleteAccountEvent->getUser();
        $em = $this->entityManager;
        $rsm = new ResultSetMapping();

        $this->deleteUserAccessData($em, $user, $rsm);
        $this->deleteRelatedEventSchedules($em, $user, $rsm);
        $this->deleteUserFromClubs($em, $user, $rsm);

        $em->createNativeQuery('DELETE FROM phone_contact_number WHERE phone_contact_id IN (
                                SELECT id FROM phone_contact WHERE owner_id = '.$user->id.')', $rsm)->execute();
        $em->createNativeQuery('DELETE FROM phone_contact WHERE owner_id = '.$user->id, $rsm)->execute();

        $em->createNativeQuery(
            'DELETE FROM follow WHERE follower_id = '.$user->id.' OR user_id = '.$user->id,
            $rsm
        )->execute();

        $rsmActivityId = new ResultSetMapping();
        $rsmActivityId->addScalarResult('id', 'id', 'uuid');
        $activityIds = $em->createNativeQuery(
            'SELECT a.id FROM activity_user au
             JOIN activity a on a.id = au.activity_id
             WHERE au.user_id = '.$user->id.'
             AND (SELECT COUNT(*) FROM activity_user au2 WHERE au2.activity_id = a.id) = 1',
            $rsmActivityId
        )->getResult();

        $em->createNativeQuery(
            'DELETE FROM activity_user WHERE activity_id IN (:activityIds)',
            $rsm
        )->setParameter('activityIds', $activityIds)->execute();

        $em->createNativeQuery(
            'DELETE FROM activity WHERE id IN (:activityIds)',
            $rsm
        )->setParameter('activityIds', $activityIds)->execute();

        $user->state = User::STATE_DELETED;
        if ($user->phone) {
            $user->oldPhoneNumber = PhoneNumberUtil::getInstance()->format($user->phone, PhoneNumberFormat::E164);
            $user->phone = null;
        }
        $user->wallet = null;
        $user->recommendedForFollowingPriority = null;
        $user->lastContactHash = null;
        $user->deleted = time();

        $this->matchingClient->publishEvent('userWalletRemoved', $user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if (!$user->isTester) {
            $message = new AmplitudeEventStatisticsMessage('api.change_state', [], $user);
            $message->userOptions['state'] = $user->state;
            $this->bus->dispatch($message);
        }
    }

    public function onBanAccount(BanAccountEvent $banAccountEvent)
    {
        $user = $banAccountEvent->getUser();
        $em = $this->entityManager;
        $rsm = new ResultSetMapping();

        $this->deleteUserAccessData($em, $user, $rsm);
        $this->deleteRelatedEventSchedules($em, $user, $rsm);
        $this->deleteUserFromClubs($em, $user, $rsm);
    }

    private function deleteUserFromClubs(EntityManagerInterface $em, User $user, ResultSetMapping $rsm)
    {
        $em->createNativeQuery(
            <<<SQL
            DELETE FROM activity WHERE join_request_id IN (
                SELECT cjr.id FROM club_join_request cjr
                WHERE cjr.author_id = {$user->id}
            )
            SQL,
            $rsm
        )->execute();
        $em->createNativeQuery('DELETE FROM club_join_request WHERE author_id = '.$user->id, $rsm)->execute();
        $em->createNativeQuery('DELETE FROM club_participant WHERE user_id = '.$user->id, $rsm)->execute();
    }

    private function deleteUserAccessData(EntityManagerInterface $em, User $user, ResultSetMapping $rsm)
    {
        $em->createNativeQuery('DELETE FROM access_token WHERE user_id = '.$user->id, $rsm)->execute();
        $em->createNativeQuery('DELETE FROM refresh_token WHERE user_id = '.$user->id, $rsm)->execute();
        $em->createNativeQuery('DELETE FROM device WHERE user_id = '.$user->id, $rsm)->execute();
    }

    private function deleteRelatedEventSchedules(EntityManagerInterface $em, User $user, ResultSetMapping $rsm)
    {
        $selectEventSchedules = 'SELECT es.id 
                                 FROM event_schedule es
                                 LEFT JOIN video_room vr ON vr.event_schedule_id = es.id
                                 WHERE vr.id IS NULL AND es.owner_id = '.$user->id;

        $em->createNativeQuery(
            'DELETE FROM event_schedule_subscription WHERE event_schedule_id IN ('.$selectEventSchedules.')',
            $rsm
        )->execute();
        $em->createNativeQuery(
            'DELETE FROM event_schedule_interest WHERE event_schedule_id IN ('.$selectEventSchedules.')',
            $rsm
        )->execute();
        $em->createNativeQuery(
            'DELETE FROM event_schedule_subscription WHERE event_schedule_id IN ('.$selectEventSchedules.')',
            $rsm
        )->execute();
        $em->createNativeQuery(
            'DELETE FROM activity_user WHERE activity_id IN (
                SELECT id FROM activity WHERE event_schedule_id IN ('.$selectEventSchedules.')
            )',
            $rsm
        )->execute();
        $em->createNativeQuery(
            'DELETE FROM activity WHERE event_schedule_id IN ('.$selectEventSchedules.')',
            $rsm
        )->execute();
        $em->createNativeQuery(
            'DELETE FROM event_schedule_participant WHERE event_id IN ('.$selectEventSchedules.') 
                                                    OR user_id = '.$user->id,
            $rsm
        )->execute();
        $em->createNativeQuery(
            'DELETE FROM event_schedule WHERE id IN ('.$selectEventSchedules.')',
            $rsm
        )->execute();
    }

    public static function getSubscribedEvents() : array
    {
        return [
            DeleteAccountEvent::class => 'onDeleteAccount',
            BanAccountEvent::class => 'onBanAccount'
        ];
    }
}
