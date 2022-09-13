<?php

namespace App\Service;

use App\Controller\ErrorCode;
use App\Entity\Activity\Activity;
use App\Entity\Activity\JoinRequestWasApprovedActivity;
use App\Entity\Activity\NewClubInviteActivity;
use App\Entity\Club\Club;
use App\Entity\Club\ClubInvite;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Event\User\UserInvitedEvent;
use App\Exception\Club\UserAlreadyJoinedToClubException;
use App\Exception\NoFreeInvitesException;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\SyncWithIntercomMessage;
use App\Repository\Activity\NewClubInviteActivityRepository;
use App\Repository\Activity\NewJoinRequestActivityRepository;
use App\Repository\Club\ClubInviteRepository;
use App\Repository\Club\ClubParticipantRepository;
use App\Repository\Club\ClubRepository;
use App\Repository\Club\JoinRequestRepository;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\Transaction\TransactionManager;
use App\Transaction\FlushEntityManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class ClubManager
{
    private TransactionManager $transactionManager;
    private InviteManager $inviteManager;
    private NotificationManager $notificationManager;
    private ActivityManager $activityManager;
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private NewJoinRequestActivityRepository $newJoinRequestActivityRepository;
    private MatchingClient $matchingClient;
    private MessageBusInterface $bus;
    private ClubParticipantRepository $clubParticipantRepository;
    private JoinRequestRepository $joinRequestRepository;
    private NewClubInviteActivityRepository $activityRepository;
    private ClubInviteRepository $clubInviteRepository;
    private LockFactory $lockFactory;

    public function __construct(
        TransactionManager $transactionManager,
        InviteManager $inviteManager,
        NotificationManager $notificationManager,
        ActivityManager $activityManager,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        NewJoinRequestActivityRepository $newJoinRequestActivityRepository,
        MatchingClient $matchingClient,
        MessageBusInterface $bus,
        ClubParticipantRepository $clubParticipantRepository,
        JoinRequestRepository $joinRequestRepository,
        NewClubInviteActivityRepository $activityRepository,
        ClubInviteRepository $clubInviteRepository,
        LockFactory $lockFactory
    ) {
        $this->transactionManager = $transactionManager;
        $this->inviteManager = $inviteManager;
        $this->notificationManager = $notificationManager;
        $this->activityManager = $activityManager;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->newJoinRequestActivityRepository = $newJoinRequestActivityRepository;
        $this->matchingClient = $matchingClient;
        $this->bus = $bus;
        $this->clubParticipantRepository = $clubParticipantRepository;
        $this->joinRequestRepository = $joinRequestRepository;
        $this->activityRepository = $activityRepository;
        $this->clubInviteRepository = $clubInviteRepository;
        $this->lockFactory = $lockFactory;
    }

    public function approveJoinRequest(JoinRequest $joinRequest, User $currentUser)
    {
        $joinRequest->status = JoinRequest::STATUS_APPROVED;

        $transactionManager = $this->transactionManager->createEmpty();
        $transactionManager
            ->addTransaction(
                fn() => $this->clubParticipantRepository->save(new ClubParticipant(
                    $joinRequest->club,
                    $joinRequest->author,
                    $currentUser
                ))
            )->addTransaction(
                fn() => $this->joinRequestRepository->save($joinRequest)
            );

        if (!$joinRequest->author->isVerifiedOrInvited()) {
            $transactionManager->merge(
                $this->inviteManager->createInviteForUser($currentUser, $joinRequest->author, $joinRequest->club)
            );
            $joinRequest->club->freeInvites--;
            $transactionManager
                ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $joinRequest->club));
        }

        $transactionManager->run();

        foreach ($this->newJoinRequestActivityRepository->findBy(['joinRequest' => $joinRequest]) as $activity) {
            $this->entityManager->remove($activity);
        }

        $participant = $this->clubParticipantRepository->findOneBy([
            'club' => $joinRequest->club,
            'user' => $currentUser
        ]);
        $role = $participant->role ?? ClubParticipant::ROLE_MEMBER;

        $activity = new JoinRequestWasApprovedActivity($joinRequest->club, $role, $joinRequest->author, $currentUser);
        $this->entityManager->persist($activity);

        $clubAvatar = $joinRequest->club->avatar;
        $this->notificationManager->sendNotifications($joinRequest->author, new ReactNativePushNotification(
            Activity::TYPE_JOIN_REQUEST_WAS_APPROVED,
            $this->activityManager->getActivityTitle($activity),
            $this->activityManager->getActivityDescription($activity),
            [
                'clubId' => $joinRequest->club->id,
                'clubTitle' => $joinRequest->club->title,
                PushNotification::PARAMETER_SPECIFIC_KEY => $activity->getType(),
                PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                PushNotification::PARAMETER_IMAGE => $currentUser->getAvatarSrc(300, 300),
                PushNotification::PARAMETER_SECOND_IMAGE => $clubAvatar ? $clubAvatar->getResizerUrl(300, 300) : null,
            ],
        ));

        $this->entityManager->flush();

        $this->matchingClient->publishEventOwnedBy(
            'userClubJoin',
            $joinRequest->author,
            ['clubId' => $joinRequest->club->id->toString(), 'role' => ClubParticipant::ROLE_MEMBER]
        );

        $this->bus->dispatch(new AmplitudeEventStatisticsMessage(
            'api.club.member_joined',
            ['slug' => $joinRequest->club->slug],
            $joinRequest->author
        ));

        $this->bus->dispatch(new SyncWithIntercomMessage($joinRequest->author));
    }

    public function addClubInviteForUser(Club $club, User $user, User $currentUser): void
    {
        $participant = $this->clubParticipantRepository->findOneBy(['club' => $club, 'user' => $user]);
        if ($participant) {
            throw new UserAlreadyJoinedToClubException();
        }

        $this->lockFactory->createLock(
            'create_invitation_link_'.$club->id->toString().'_user_'.$user->id
        )->acquire(true);

        if (!$this->activityRepository->findOneBy(['club' => $club, 'user' => $user])) {
            $activity = new NewClubInviteActivity($club, $user, $currentUser);
            $this->activityManager->fireActivityForUsers(
                $activity,
                [$user],
                new ReactNativePushNotification(
                    $activity->getType(),
                    $this->activityManager->getActivityTitle($activity),
                    $this->activityManager->getActivityDescription($activity),
                    [
                        PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                        PushNotification::PARAMETER_IMAGE => $club->avatar ?
                            $club->avatar->getResizerUrl(300, 300) :
                            null,
                        'clubId' => $club->id->toString(),
                    ]
                )
            );
        }

        $joinRequest = $this->joinRequestRepository->findOneBy(['club' => $club, 'author' => $user]);
        if ($joinRequest && $joinRequest->status == JoinRequest::STATUS_MODERATION) {
            $this->approveJoinRequest($joinRequest, $currentUser);
            return;
        }

        $this->decrementFreeInvites($club);

        $clubInvite = $this->clubInviteRepository->findOneBy(['club' => $club, 'user' => $user]);
        if (!$clubInvite) {
            $this->clubInviteRepository->save(new ClubInvite($club, $user, $currentUser));
        } else {
            $clubInvite->notificationSendAt = time();
            $this->clubInviteRepository->save($clubInvite);
        }
    }

    public function getFreeInvites(Club $club): int
    {
        $this->lockFactory->createLock('club_'.$club->id->toString().'_working_with_free_invites')->acquire(true);

        $this->entityManager->refresh($club);

        return $club->freeInvites;
    }

    public function decrementFreeInvites(Club $club, int $decrementInvites = 1)
    {
        $this->lockFactory->createLock('club_'.$club->id->toString().'_working_with_free_invites')->acquire(true);

        $this->entityManager->refresh($club);

        if ($club->freeInvites < $decrementInvites) {
            throw new NoFreeInvitesException();
        }

        $club->freeInvites -= $decrementInvites;
        $this->entityManager->persist($club);
        $this->entityManager->flush();
    }
}
