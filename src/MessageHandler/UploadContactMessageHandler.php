<?php

namespace App\MessageHandler;

use App\DTO\V1\User\ContactPhoneRequest;
use App\Message\UploadContactMessage;
use App\Repository\User\PhoneContactRepository;
use App\Repository\UserRepository;
use App\Service\MatchingClient;
use App\Service\PhoneContactManager;
use App\Service\PhoneNumberManager;
use App\Service\ValueObject\ContactPhone;
use App\Transaction\FlushEntityManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

final class UploadContactMessageHandler implements MessageHandlerInterface
{
    private PhoneContactManager $phoneContactManager;
    private PhoneNumberManager $phoneNumberManager;
    private EntityManagerInterface $em;
    private PhoneContactRepository $phoneContactRepository;
    private MatchingClient $matchingClient;
    private UserRepository $userRepository;

    public function __construct(
        PhoneContactManager $phoneContactManager,
        PhoneNumberManager $phoneNumberManager,
        EntityManagerInterface $em,
        PhoneContactRepository $phoneContactRepository,
        MatchingClient $matchingClient,
        UserRepository $userRepository
    ) {
        $this->phoneContactManager = $phoneContactManager;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->em = $em;
        $this->phoneContactRepository = $phoneContactRepository;
        $this->matchingClient = $matchingClient;
        $this->userRepository = $userRepository;
    }

    public function __invoke(UploadContactMessage $message): void
    {
        $currentUser = $this->userRepository->find($message->getUserId());
        if (!$currentUser) {
            return;
        }

        $region = $currentUser->getPhoneNumberRegion();
        $loadContactRequest = $message->getLoadPhoneContactsRequest();

        $phoneNumbers = [];
        $util = PhoneNumberUtil::getInstance();
        foreach ($loadContactRequest->contacts as $k => $contact) {
            try {
                $contactMainPhoneNumber = $contact->phoneNumber ?? $contact->phoneNumbers[0] ?? null;
                if (!$contactMainPhoneNumber) {
                    continue;
                }

                $phoneNumber = $this->phoneNumberManager->parse($contactMainPhoneNumber, $region);
                $type = $util->getNumberType($phoneNumber);

                if (false === in_array($type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE])) {
                    unset($loadContactRequest->contacts[$k]);
                    continue;
                }

                $phoneNumbers[$util->format($phoneNumber, PhoneNumberFormat::E164)] = $phoneNumber;
            } catch (Throwable $e) {
                unset($loadContactRequest->contacts[$k]);
            }
        }

        /** @var ContactPhone[] $contacts */
        $contacts = array_map(
            fn(ContactPhoneRequest $r) => new ContactPhone(
                $r->fullName,
                $r->phoneNumber ? [$r->phoneNumber] : $r->phoneNumbers,
                $r->thumbnail
            ),
            $loadContactRequest->contacts
        );
        $transactionManager = $this->phoneContactManager->uploadContacts($currentUser, $contacts);

        $currentUser->lastContactHash = $message->getCalculatedChangesHash();
        $currentUser->lockContactsUpload = 0;

        $numbers = [];
        foreach ($contacts as $contact) {
            foreach ($contact->phoneNumbers as $phoneNumber) {
                $numbers[] = $phoneNumber;
            }
        }
        $numbers = array_unique($numbers);

        $userIds = $this->phoneContactRepository->findUserIdsByPhoneNumbers($numbers);

        $transactionManager
            ->addTransaction(new FlushEntityManagerTransaction($this->em, $currentUser))
            ->addTransaction(fn() => $this->phoneContactManager->uploadContactsToElasticSearch($currentUser))
            ->addTransaction(
                fn() => $this->matchingClient->publishEventOwnedBy(
                    'userContactsUpdated',
                    $currentUser,
                    ['userIds' => $userIds]
                )
            )
            ->run();
    }
}
