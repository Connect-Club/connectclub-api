<?php

namespace App\Controller\V1;

use App\Annotation\Security;
use App\Controller\BaseController;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\User\ContactPhoneRequest;
use App\DTO\V1\User\LoadPhoneContactsRequest;
use App\DTO\V1\User\PhoneContactNumberResponse;
use App\DTO\V1\User\PhoneContactResponse;
use App\Entity\User\PhoneContactNumber;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Entity\User\PhoneContact;
use App\Message\UploadPhoneContactsMessage;
use App\Repository\Invite\InviteRepository;
use App\Repository\User\PhoneContactNumberRepository;
use App\Repository\User\PhoneContactRepository;
use App\Service\PhoneContactManager;
use App\Service\PhoneNumberManager;
use App\Service\ValueObject\ContactPhone;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use App\Transaction\FlushRemoveManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * @Route("/contact-phone")
 */
class ContactPhoneController extends BaseController
{
    private PhoneContactRepository $phoneContactRepository;
    private PhoneNumberManager $phoneNumberManager;

    public function __construct(PhoneContactRepository $phoneContactRepository, PhoneNumberManager $phoneNumberManager)
    {
        $this->phoneContactRepository = $phoneContactRepository;
        $this->phoneNumberManager = $phoneNumberManager;
    }

    /**
     * @Security(role="ROLE_USER")
     * @SWG\Post(
     *     description="Upload phone contacts",
     *     summary="Upload phone contacts",
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=LoadPhoneContactsRequest::class))),
     *     @SWG\Response(response="200", description="Success")
     * )
     * @Route("", methods={"POST"})
     * @ViewResponse()
     */
    public function upload(
        Request $request,
        PhoneContactManager $phoneContactManager,
        LockFactory $lock
    ): JsonResponse {
        $currentUser = $this->getUser();

        $key = 'upload_contacts_'.$currentUser->id ?? 0;
        $lock = $lock->createLock($key, 1, false);
        if (!$lock->acquire()) {
            return $this->handleResponse([]);
        }

        if ($currentUser->phone) {
            $region = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($currentUser->phone);
        }
        $region = $region ?? PhoneNumberUtil::UNKNOWN_REGION;

        /** @var LoadPhoneContactsRequest $loadContactRequest */
        $loadContactRequest = $this->getEntityFromRequestTo($request, LoadPhoneContactsRequest::class);

        $phoneNumbers = [];
        $util = PhoneNumberUtil::getInstance();
        foreach ($loadContactRequest->contacts as $k => $contact) {
            try {
                $contactMainPhoneNumber = $contact->phoneNumber ?? $contact->phoneNumbers[0] ?? null;

                $phoneNumber = $util->parse($contactMainPhoneNumber, $region);
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

        $transactionManager = $phoneContactManager->uploadContacts(
            $currentUser,
            array_map(
                fn(ContactPhoneRequest $r) => new ContactPhone(
                    $r->fullName,
                    $r->phoneNumber ? [$r->phoneNumber] : $r->phoneNumbers,
                    $r->thumbnail
                ),
                $loadContactRequest->contacts
            )
        );

        $transactionManager->run();

        $lock->release();

        return $this->handleResponse([]);
    }

    /**
     * @Security(role="ROLE_USER")
     * @SWG\Get(
     *     description="Get phone contacts",
     *     summary="Get phone contacts",
     *     tags={"User"},
     *     @SWG\Parameter(in="query", name="search", type="string"),
     *     @SWG\Response(response="200", description="Success")
     * )
     * @Route("", methods={"GET"})
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=PhoneContactResponse::class)
     */
    public function all(
        Request $request,
        InviteRepository $inviteRepository,
        PhoneContactNumberRepository $phoneContactNumberRepository
    ): JsonResponse {
        $user = $this->getUser();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 1000);
        $query = $request->query->get('search');

        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        $phone = null;
        if ($query) {
            if ($user->phone) {
                $region = $phoneNumberUtil->getRegionCodeForNumber($user->phone);
            }
            $region = $region ?? PhoneNumberUtil::UNKNOWN_REGION;

            $phoneNumbers = $phoneNumberUtil->findNumbers($query, $region);

            $detectedPhone = $phoneNumbers->current();
            if ($detectedPhone) {
                $phone = $phoneNumberUtil->format($detectedPhone->number(), PhoneNumberFormat::E164);
            } else {
                $queryPhoneNumber = preg_replace('[^0-9-+]', '', $query);

                $variants = [];
                $variants[] = [$queryPhoneNumber, PhoneNumberUtil::UNKNOWN_REGION];
                if (mb_substr($queryPhoneNumber, 0, 1) != '+') {
                    $variants[] = ['+'.$queryPhoneNumber, PhoneNumberUtil::UNKNOWN_REGION];
                }
                $variants[] = [$queryPhoneNumber, $region];

                foreach ($variants as list($variant, $region)) {
                    try {
                        $phoneNumber = $phoneNumberUtil->parse($variant, $region);
                        if ($phoneNumber !== null) {
                            break;
                        }
                    } catch (NumberParseException $exception) {
                        $phoneNumber = null;
                    }
                }

                if ($phoneNumber) {
                    $type = $phoneNumberUtil->getNumberType($phoneNumber);
                    $isMobile = in_array($type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE]);
                    if ($isMobile) {
                        $phone = $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);
                    } else {
                        $phoneNumber = null;
                    }
                }
            }
        }

        list($phones, $lastValue) = $this->phoneContactRepository->findContactPhoneNumbers(
            $user,
            $phone,
            $query,
            $lastValue,
            $limit
        );
        $phones = array_map('array_values', $phones);

        $phoneContactIds = [];
        /** @var PhoneContact $phoneContact */
        foreach ($phones as list($phoneContact,)) {
            $phoneContactIds[] = $phoneContact->id->toString();
        }

        $additionalPhoneNumbers = $this->phoneNumberManager->findPhoneNumbersDataForContacts($phoneContactIds);
        $phoneNumbers = $phoneContactNumberRepository->findAllPhoneNumbersForContactIds($phoneContactIds);

        $response = [];
        /** @var PhoneContact $phoneContact */
        foreach ($phones as list($phoneContact, $count, $isInvited, $isPending)) {
            if ($isInvited) {
                $status = 'invited';
            } else {
                $status = $isPending ? 'pending' : 'new';
            }

            $response[] = new PhoneContactResponse(
                $phoneNumbers[$phoneContact->id->toString()] ?? [],
                array_map(
                    fn(array $row) => new PhoneContactNumberResponse($row[0], $row[1]),
                    $additionalPhoneNumbers[$phoneContact->id->toString()] ?? []
                ),
                $phoneContact->fullName,
                $status,
                $count,
                $phoneContact->phoneNumber,
                $phoneContact->thumbnail
            );
        }

        if (isset($phoneNumber) && !$response) {
            $invite = $inviteRepository->findInviteByAuthorAndPhoneNumber($user, $phoneNumber);

            $status = $invite ? 'send_reminder' : 'unknown';

            $response[] = new PhoneContactResponse(
                [$phoneNumber],
                [
                    new PhoneContactNumberResponse(
                        $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164),
                        $status
                    )
                ],
                $phone,
                $status,
                0,
                null
            );
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @Security(role="ROLE_USER")
     * @SWG\Get(
     *     description="Get pending phone contacts",
     *     summary="Get pending phone contacts",
     *     tags={"User"},
     *     @SWG\Response(response="200", description="Success")
     * )
     * @Route("/pending", methods={"GET"})
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=PhoneContactResponse::class)
     */
    public function pending(Request $request, PhoneContactNumberRepository $phoneContactNumberRepository): JsonResponse
    {
        $user = $this->getUser();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 200);

        list($invites, $lastValue) = $this->phoneContactRepository->findPendingPhoneContacts($user, $lastValue, $limit);
        $invites = array_map('array_values', $invites);

        $util = PhoneNumberUtil::getInstance();
        $phoneNumbersFromInvites = array_map(
            fn($invite) => $util->format($invite[0]->phoneNumber, PhoneNumberFormat::E164),
            $invites
        );

        $phoneContactNumbers = [];
        /** @var PhoneContactNumber $number */
        foreach ($phoneContactNumberRepository->findPhoneNumberContacts($user, $phoneNumbersFromInvites) as $number) {
            $phoneContactNumbers[$util->format($number->phoneNumber, PhoneNumberFormat::E164)] = $number->phoneContact;
        }

        $phoneContactIds = array_map(fn(PhoneContact $c) => $c->id->toString(), $phoneContactNumbers);
        $additionalPhoneNumbers = $this->phoneNumberManager->findPhoneNumbersDataForContacts($phoneContactIds);

        $additionalPhoneNumbersFromInvites = $this->phoneNumberManager->findPhoneNumbersDataForNumbers(
            $user,
            $phoneNumbersFromInvites
        );

        $response = [];
        /** @var Invite $invite */
        foreach ($invites as list($invite, $count)) {
            $inviteFormattedPhoneNumber = $util->format($invite->phoneNumber, PhoneNumberFormat::E164);

            $phoneContact = $phoneContactNumbers[$inviteFormattedPhoneNumber] ?? null;
            if ($phoneContact) {
                $numbers = $phoneContact->phoneNumbers->map(fn(PhoneContactNumber $n) => $n->phoneNumber)->toArray();
                $additionalNumbers = $additionalPhoneNumbers[$phoneContact->id->toString()] ?? [];
                $fullName = $phoneContact->fullName;
                $thumbnail = $phoneContact->thumbnail;
            } else {
                $numbers = [$invite->phoneNumber];
                $fullName = $util->format($invite->phoneNumber, PhoneNumberFormat::E164);
                $additionalNumbers = [[$fullName, 'pending']];
                $thumbnail = null;
            }

            $key = $phoneContact ? $phoneContact->id->toString() : $inviteFormattedPhoneNumber;

            $response[$key] = new PhoneContactResponse(
                $numbers,
                array_map(
                    fn(array $row) => new PhoneContactNumberResponse($row[0], $row[1]),
                    $additionalNumbers
                ),
                $fullName,
                'pending',
                $count,
                $invite->phoneNumber,
                $thumbnail
            );
        }

        return $this->handleResponse(new PaginatedResponse(array_values($response), $lastValue));
    }
}
