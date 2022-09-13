<?php

namespace App\MessageHandler;

use App\Exception\Club\UserAlreadyJoinedToClubException;
use App\Message\InviteAllNetworkToClubMessage;
use App\Repository\Club\ClubRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\UserRepository;
use App\Service\ClubManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final class InviteAllNetworkToClubMessageHandler implements MessageHandlerInterface
{
    private ClubRepository $clubRepository;
    private UserRepository $userRepository;
    private FollowRepository $followRepository;
    private LockFactory $lockFactory;
    private ClubManager $clubManager;
    private MessageBusInterface $bus;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        ClubRepository $clubRepository,
        UserRepository $userRepository,
        FollowRepository $followRepository,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        ClubManager $clubManager,
        MessageBusInterface $bus,
        LockFactory $lockFactory
    ) {
        $this->clubRepository = $clubRepository;
        $this->userRepository = $userRepository;
        $this->followRepository = $followRepository;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->clubManager = $clubManager;
        $this->bus = $bus;
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(InviteAllNetworkToClubMessage $message)
    {
        $user = $this->userRepository->find($message->getAuthorId());
        if (!$user) {
            return;
        }

        $clubId = $message->getClubId();
        if (!Uuid::isValid($clubId)) {
            return;
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return;
        }

        if (0 === $club->freeInvites) {
            $this->logger->warning('Club no free invites', ['clubId' => $clubId]);
            return;
        }

        try {
            [$friends, $lastValue] = $this->followRepository->findFriendsFollowers(
                $user,
                null,
                $message->getLastValue(),
                300,
                null,
                $clubId,
                true
            );

            foreach ($friends as $friend) {
                try {
                    $this->clubManager->addClubInviteForUser($club, $friend, $user);
                } catch (UserAlreadyJoinedToClubException $userAlreadyJoinedToClubException) {
                }
            }

            if ($lastValue) {
                $message->setLastValue($lastValue);
                $this->bus->dispatch($message);
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception, ['exception' => $exception]);
        }
    }
}
