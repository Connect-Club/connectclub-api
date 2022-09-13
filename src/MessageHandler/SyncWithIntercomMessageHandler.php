<?php

namespace App\MessageHandler;

use App\Client\IntercomClient;
use App\Entity\User;
use App\Exception\IntercomContactAlreadyExistsException;
use App\Message\SyncWithIntercomMessage;
use App\Repository\UserRepository;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class SyncWithIntercomMessageHandler implements MessageHandlerInterface
{
    private IntercomClient $intercomClient;
    private UserRepository $userRepository;
    private LockFactory $lockFactory;

    public function __construct(
        IntercomClient $intercomClient,
        UserRepository $userRepository,
        LockFactory $lockFactory
    ) {
        $this->intercomClient = $intercomClient;
        $this->userRepository = $userRepository;
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(SyncWithIntercomMessage $message)
    {
        if ($_ENV['STAGE'] == 1) {
            return;
        }

        $user = $this->userRepository->findOneBy(['id' => $message->getUserId(), 'state' => User::STATE_VERIFIED]);
        if (!$user) {
            return;
        }

        $clubSlugs = '';
        foreach ($user->clubParticipants as $clubParticipant) {
            $clubSlugs = '|'.$clubParticipant->club->slug.'|';
        }
        $customAttributes = ['club_slug' => $clubSlugs];
        $calculatedIntercomHash = $this->intercomClient->getContactHash($user, $customAttributes);

        $user->intercomHash = $calculatedIntercomHash;
        if (!$user->intercomId) {
            if (!$this->lockFactory->createLock('register_user_in_intercom_'.$user->id)->acquire()) {
                return;
            }

            try {
                $intercomData = $this->intercomClient->registerContact($user, $customAttributes);
                $user->intercomId = $intercomData['id'] ?? $user->intercomId;
            } catch (IntercomContactAlreadyExistsException $exception) {
                $contact = $this->intercomClient->findIntercomContact($user);
                $user->intercomId = $contact['id'] ?? null;
            }
        } else {
            $this->intercomClient->updateContact($user, $customAttributes);
        }

        $this->userRepository->save($user);
    }
}
