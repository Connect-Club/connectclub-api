<?php

namespace App\Service;

use App\Entity\Community\Community;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoomBan;
use App\Event\User\BanAccountEvent;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoRoomBanRepository;
use App\Service\Transaction\TransactionManager;
use App\Transaction\FlushEntityManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BanManager
{
    private TransactionManager $transactionManager;
    private EntityManagerInterface $entityManager;
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private JitsiEndpointManager $jitsiEndpointManager;
    private VideoRoomBanRepository $videoRoomBanRepository;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        TransactionManager $transactionManager,
        EntityManagerInterface $entityManager,
        JitsiEndpointManager $jitsiEndpointManager,
        VideoRoomBanRepository $videoRoomBanRepository,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->transactionManager = $transactionManager;
        $this->entityManager = $entityManager;
        $this->jitsiEndpointManager = $jitsiEndpointManager;
        $this->videoRoomBanRepository = $videoRoomBanRepository;
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function createBanUserInCommunityTransactions(Community $community, User $user): TransactionManager
    {
        $transactionManager = $this->transactionManager->createEmpty();

        if (!$videoRoomBan = $this->videoRoomBanRepository->findBan($user, $community->videoRoom)) {
            $videoRoomBan = new VideoRoomBan($community->videoRoom, $user);
        }

        $transactionManager
            ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $videoRoomBan))
            ->addTransaction(fn() => $this->jitsiEndpointManager->disconnectUserFromRoom($user, $community->videoRoom));

        return $transactionManager;
    }

    public function createBanUserTransactions(User $bannedBy, User $user, ?string $comment = null): TransactionManager
    {
        $transactionManager = $this->transactionManager->createEmpty();

        $user->state = User::STATE_BANNED;
        $user->bannedAt = time();
        $user->bannedBy = $bannedBy;
        $user->banComment = $comment;
        $transactionManager->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $user));

        $onlineSessionsAsParticipant = $this->videoMeetingParticipantRepository->findBy([
            'participant' => $user,
            'endTime' => null
        ]);

        foreach ($onlineSessionsAsParticipant as $participant) {
            $room = $participant->videoMeeting->videoRoom;
            $transactionManager->addTransaction(
                fn() => $this->jitsiEndpointManager->disconnectUserFromRoom($user, $room)
            );
        }

        $transactionManager->addTransaction(fn() => $this->eventDispatcher->dispatch(new BanAccountEvent($user)));

        return $transactionManager;
    }

    public function createUnbanUserTransactions(User $user): TransactionManager
    {
        $user->bannedAt = null;
        $user->state = User::STATE_VERIFIED;

        return $this->transactionManager
                    ->createEmpty()
                    ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $user));
    }
}
