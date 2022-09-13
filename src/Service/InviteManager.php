<?php

namespace App\Service;

use App\Entity\Club\Club;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Event\User\UserInvitedEvent;
use App\Exception\NoFreeInvitesException;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\Invite\InviteRepository;
use App\Service\Transaction\TransactionManager;
use App\Transaction\FlushEntityManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class InviteManager
{
    private LockFactory $lockFactory;
    private PhoneNumberManager $phoneNumberManager;
    private InviteRepository $inviteRepository;
    private EntityManagerInterface $entityManager;
    private TransactionManager $transactionManager;
    private EventDispatcherInterface $eventDispatcher;
    private MessageBusInterface $bus;

    public function __construct(
        LockFactory $lockFactory,
        PhoneNumberManager $phoneNumberManager,
        InviteRepository $inviteRepository,
        EntityManagerInterface $entityManager,
        TransactionManager $transactionManager,
        EventDispatcherInterface $eventDispatcher,
        MessageBusInterface $bus
    ) {
        $this->lockFactory = $lockFactory;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->inviteRepository = $inviteRepository;
        $this->entityManager = $entityManager;
        $this->transactionManager = $transactionManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $bus;
    }

    public function createInviteForUser(User $invitedBy, User $user, ?Club $club = null): TransactionManager
    {
        if (!in_array($user->state, [User::STATE_NOT_INVITED, User::STATE_WAITING_LIST])) {
            throw new LogicException('Cannot create invite for user state '.$user->state);
        }

        if (!$club) {
            $this->lockFactory->createLock('create_invite_by_'.$invitedBy->id)->acquire(true);
            $this->entityManager->refresh($invitedBy);

            if ($invitedBy->freeInvites < 1) {
                throw new NoFreeInvitesException();
            }
        }

        $userId = $user->phone ? $this->phoneNumberManager->formatE164($user->phone) : (string) $user->id;
        $lock = $this->lockFactory->createLock('create_invite_for_'.$userId);
        $lock->acquire(true);
        $this->entityManager->refresh($user);

        if (!in_array($user->state, [User::STATE_NOT_INVITED, User::STATE_WAITING_LIST])) {
            return $this->transactionManager->createEmpty();
        }

        if ($user->phone && $this->inviteRepository->findActiveInviteWithPhoneNumber($user->phone)) {
            return $this->transactionManager->createEmpty();
        }

        $invite = new Invite($invitedBy, $user->phone);
        if ($club) {
            $invite->club = $club;
        }
        $invite->registeredUser = $user;

        $user->state = User::STATE_INVITED;
        $invitedBy->freeInvites -= 1;

        $message = new AmplitudeEventStatisticsMessage('api.change_state', [], $user);
        $message->userOptions['state'] = $user->state;

        return $this->transactionManager
             ->createEmpty()
             ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $invitedBy))
             ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $user))
             ->addTransaction(new FlushEntityManagerTransaction($this->entityManager, $invite))
             ->addTransaction(fn() => $this->bus->dispatch($message))
             ->addTransaction(fn() => $this->eventDispatcher->dispatch(new UserInvitedEvent($user)));
    }
}
