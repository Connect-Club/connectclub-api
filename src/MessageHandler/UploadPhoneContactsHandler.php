<?php

namespace App\MessageHandler;

use App\Message\UploadPhoneContactsMessage;
use App\Repository\User\PhoneContactRepository;
use App\Repository\UserRepository;
use App\Service\PhoneContactManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class UploadPhoneContactsHandler implements MessageHandlerInterface
{
    private UserRepository $userRepository;
    private PhoneContactManager $phoneContactManager;
    private LoggerInterface $logger;

    public function __construct(
        UserRepository $userRepository,
        PhoneContactManager $phoneContactManager,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->phoneContactManager = $phoneContactManager;
        $this->logger = $logger;
    }

    public function __invoke(UploadPhoneContactsMessage $message)
    {
        $owner = $this->userRepository->find($message->getUserId());

        if (!$owner) {
            $this->logger->error('Contact owner not found', ['owner_id' => $message->getUserId()]);
            return;
        }

        $this->phoneContactManager->uploadContacts($owner, $message->getContacts())->run();
    }
}
