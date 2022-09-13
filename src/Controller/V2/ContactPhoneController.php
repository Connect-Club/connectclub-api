<?php

namespace App\Controller\V2;

use App\Annotation\Security;
use App\Client\ElasticSearchClientBuilder;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\User\ContactPhoneRequest;
use App\DTO\V1\User\LoadPhoneContactsRequest;
use App\DTO\V1\User\PhoneContactNumberResponse;
use App\DTO\V1\User\PhoneContactResponse;
use App\Entity\User\PhoneContactNumber;
use App\Entity\User;
use App\Entity\User\PhoneContact;
use App\Message\UploadContactMessage;
use App\Message\UploadPhoneContactsMessage;
use App\Repository\Invite\InviteRepository;
use App\Repository\User\PhoneContactNumberRepository;
use App\Repository\User\PhoneContactRepository;
use App\Repository\UserRepository;
use App\Service\PhoneContactManager;
use App\Service\PhoneNumberManager;
use App\Service\ValueObject\ContactPhone;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use App\Transaction\FlushEntityManagerTransaction;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/contact-phone")
 */
class ContactPhoneController extends BaseController
{
    private PhoneContactRepository $phoneContactRepository;

    public function __construct(PhoneContactRepository $phoneContactRepository)
    {
        $this->phoneContactRepository = $phoneContactRepository;
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
        MessageBusInterface $bus,
        UserRepository $userRepository
    ): JsonResponse {
        $currentUser = $this->getUser();

        $contactHash = md5($request->getContent());
        if ($currentUser->lastContactHash === $contactHash) {
            return $this->handleResponse([]);
        }

        if ($currentUser->lockContactsUpload > time()) {
            return $this->handleResponse([]);
        }

        $currentUser->lockContactsUpload = time() + 120;
        $userRepository->save($currentUser);

        /** @var LoadPhoneContactsRequest $loadContactRequest */
        $loadContactRequest = $this->getEntityFromRequestTo($request, LoadPhoneContactsRequest::class);

        $bus->dispatch(new UploadContactMessage($currentUser->id, $loadContactRequest, $contactHash));

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
     * @ListResponse(
     *     pagination=true,
     *     paginationByLastValue=true,
     *     entityClass=PhoneContactResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_LOCKED, ErrorCode::V1_CONTACT_PHONE_NOT_READY_YET, "Uploaded contacts not processed yet"}
     *     }
     * )
     */
    public function all(
        Request $request,
        UserRepository $userRepository,
        PhoneNumberManager $phoneNumberManager,
        InviteRepository $inviteRepository,
        ElasticSearchClientBuilder $elasticSearchClientBuilder
    ): JsonResponse {
        $user = $this->getUser();

        if ($user->lockContactsUpload > time()) {
            return $this->createErrorResponse(ErrorCode::V1_CONTACT_PHONE_NOT_READY_YET, Response::HTTP_LOCKED);
        }

        $elasticSearchClient = $elasticSearchClientBuilder->createClient();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 50);
        $query = $request->query->get('search');

        $ownerMatchFilter = [
            'match' => [
                'ownerId' => $user->id,
            ],
        ];

        if ($query) {
            $searchQueryBody = [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => $query,
                                'operator' => 'and',
                                'fields' => ['fullName', 'phoneNumber', 'phoneNumbers'],
                            ],
                        ],
                        $ownerMatchFilter
                    ],
                ],
            ];
        } else {
            $searchQueryBody = $ownerMatchFilter;
        }

        $searchBody = [
            'sort' => [
                'sortNumber' => 'asc',
            ],
            'size' => $limit,
            'query' => $searchQueryBody,
        ];

        if ($lastValue) {
            $searchBody['search_after'] = [$lastValue];
        }

        $items = $elasticSearchClient->search(['index' => 'phone_contact', 'type' => '_doc', 'body' => $searchBody]);
        $phoneContactIds = array_map(fn(array $item) => $item['_id'], $items['hits']['hits']);

        $hits = $items['hits']['hits'];
        if ($hits) {
            $lastValueFromElasticSearch = $hits[count($hits) - 1]['sort'][0];
            if ($lastValueFromElasticSearch == $lastValue || $lastValueFromElasticSearch === 0) {
                $lastValue = null;
            } else {
                $lastValue = $lastValueFromElasticSearch;
            }
        } else {
            $lastValue = null;
        }

        $phone = null;
        if ($query) {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();

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

        $phones = $this->phoneContactRepository->findContactsByUserId($user, $phoneContactIds);
        $phones = array_map('array_values', $phones);

        $additionalPhoneNumbers = $phoneNumberManager->findPhoneNumbersDataForContacts($phoneContactIds);

        $response = [];
        /** @var PhoneContact $phoneContact */
        foreach ($phones as list($phoneContact, $count, $isInvited, $isPending)) {
            if ($isInvited) {
                $status = 'invited';
            } else {
                $status = $isPending ? 'pending' : 'new';
            }
            $response[] = new PhoneContactResponse(
                $phoneContact->phoneNumbers->map(fn(PhoneContactNumber $n) => $n->phoneNumber)->toArray(),
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
            $invite = $inviteRepository->findInviteByAuthorAndPhoneNumber(
                $user,
                $phoneNumber
            );

            if ($registeredUser = $userRepository->findUserByPhoneNumber($phoneNumber)) {
                if ($registeredUser->state === User::STATE_INVITED) {
                    $status = $invite ? 'send_reminder' : 'invited';
                } elseif ($registeredUser->isVerified()) {
                    $status = 'invited';
                } else {
                    $status = $invite ? 'send_reminder' : 'unknown';
                }
            } else {
                $status = $invite ? 'send_reminder' : 'unknown';
            }

            $response[] = new PhoneContactResponse(
                [$phoneNumber],
                [new PhoneContactNumberResponse($phone, $status)],
                $phone,
                $status,
                0,
                null
            );
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }
}
