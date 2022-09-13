<?php

namespace App\Controller\V2\User;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\Reference\ReferenceResponse;
use App\DTO\V2\User\CurrentUserResponse;
use App\DTO\V1\User\UserResponse;
use App\DTO\V2\User\AccountPatchProfileRequest;
use App\DTO\V2\User\ImportFromFacebookRequest;
use App\DTO\V2\User\LanguageDTO;
use App\Entity\Activity\Activity;
use App\Entity\Activity\InviteWelcomeOnBoardingActivity;
use App\Entity\Activity\NewUserFromWaitingListActivity;
use App\Entity\Activity\UserRegisteredActivity;
use App\Entity\Activity\WelcomeOnBoardingFriendActivity;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventDraft;
use App\Entity\User;
use App\Event\User\ChangeStateUserEvent;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\CheckAvatarPhotoTheHiveAiMessage;
use App\Message\SyncWithIntercomMessage;
use App\Message\UploadUserToElasticsearchMessage;
use App\Repository\Club\ClubParticipantRepository;
use App\Repository\Interest\InterestRepository;
use App\Repository\Matching\GoalRepository;
use App\Repository\Matching\IndustryRepository;
use App\Repository\Matching\SkillRepository;
use App\Repository\Photo\UserPhotoRepository;
use App\Repository\User\LanguageRepository;
use App\Repository\User\PhoneContactRepository;
use App\Repository\UserRepository;
use App\Service\ActivityManager;
use App\Service\EventLogManager;
use App\Service\LanguageManager;
use App\Service\MatchingClient;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\UserService;
use App\Service\InviteManager;
use App\Service\VideoRoomManager;
use App\Exception\NoFreeInvitesException;
use App\Swagger\ViewResponse;
use App\Util\BlackListUsernameStore;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * Class AccountController.
 *
 * @Route("/account")
 */
class AccountController extends BaseController
{
    private EntityManagerInterface $entityManager;
    private PhoneContactRepository $phoneContactRepository;
    private NotificationManager $notificationManager;
    private ActivityManager $activityManager;
    private VideoRoomManager $videoRoomManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        PhoneContactRepository $phoneContactRepository,
        NotificationManager $notificationManager,
        ActivityManager $activityManager,
        VideoRoomManager $videoRoomManager
    ) {
        $this->entityManager = $entityManager;
        $this->phoneContactRepository = $phoneContactRepository;
        $this->notificationManager = $notificationManager;
        $this->activityManager = $activityManager;
        $this->videoRoomManager = $videoRoomManager;
    }

    /**
     * @SWG\Get(
     *     produces={"application/json"},
     *     tags={"Account"},
     *     summary="Get user info about current user",
     *     @SWG\Response(response=200, description="Success response"),
     * )
     * @ViewResponse(entityClass=UserResponse::class)
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("", methods={"GET"})
     * @Security("not(is_granted('ROLE_USER_OLD'))")
     */
    public function current(
        Request $request,
        LanguageManager $languageManager,
        UserRepository $repository,
        ClubParticipantRepository $clubParticipantRepository
    ): JsonResponse {
        $currentUser = $this->getUser();

        if ($currentUser->nativeLanguages->isEmpty()) {
            $ip = $request->getClientIp();
            $language = $languageManager->findLanguageByIp($ip);

            if ($language) {
                $currentUser->addNativeLanguage($language);
                $repository->save($currentUser);
            }
        }

        $invitedByClubRole = null;
        if ($currentUser->invite && $currentUser->invite->club) {
            $invitedByParticipantClub = $clubParticipantRepository->findOneBy([
                'club' => $currentUser->invite->club,
                'user' => $currentUser->invite->author
            ]);

            $invitedByClubRole = $invitedByParticipantClub->role ?? null;
        }

        return $this->handleResponse(new CurrentUserResponse($currentUser, $invitedByClubRole));
    }

    /**
     * @SWG\Patch(
     *     produces={"application/json"},
     *     tags={"Account"},
     *     summary="Patch current profile data",
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Response(response=422, description="Validation errors"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         @SWG\Schema(ref=@Nelmio\Model(type=AccountPatchProfileRequest::class))
     *     )
     * )
     * @ViewResponse(
     *     entityClass=CurrentUserResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "name:cannot_be_empty", "Name validation error"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "surname:cannot_be_empty", "Surname validation error"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "about:cannot_be_empty", "About validation error"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "username:incorrect_value", "Incorrect value for username"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::V1_USER_USERNAME_ALREADY_EXISTS, "Username exists"},
     *     }
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("", methods={"PATCH"})
     * @Security("not(is_granted('ROLE_USER_OLD'))")
     */
    public function updateProfile(
        Request $request,
        UserRepository $userRepository,
        UserPhotoRepository $userPhotoRepository,
        MessageBusInterface $bus,
        InterestRepository $interestRepository,
        LanguageRepository $languageRepository,
        IndustryRepository $industryRepository,
        SkillRepository $skillRepository,
        GoalRepository $goalRepository,
        MatchingClient $matchingClient,
        InviteManager $inviteManager,
        LoggerInterface $logger
    ): JsonResponse {
        /** @var AccountPatchProfileRequest $patchProfileRequest */
        $patchProfileRequest = $this->getEntityFromRequestTo($request, AccountPatchProfileRequest::class);

        $this->unprocessableUnlessValid($patchProfileRequest);

        $use_autoinvite = 0;

        $user = $this->getUser();

        $username = mb_strtolower($patchProfileRequest->username);
        if (in_array($username, BlackListUsernameStore::BLACK_LIST_USERNAMES)) {
            return $this->createErrorResponse(ErrorCode::V1_USER_USERNAME_ALREADY_EXISTS);
        }

        /* TODO This code will invite everyone on register, not only wallet holders */
        if (!$user->username && $user->state === User::STATE_NOT_INVITED && $username && !$user->invite
            && !$user->inviteCode/* && $user->wallet*/

        ) {
            $use_autoinvite = 1;
        }

        if ($username) {
            $usernameAlreadyExist = $userRepository
                ->createQueryBuilder('u')
                ->where('LOWER(u.username) = :username')
                ->andWhere('u.id != :id')
                ->setMaxResults(1)
                ->setFirstResult(0)
                ->getQuery()
                ->setParameter('username', $username)
                ->setParameter('id', $user->id)
                ->getOneOrNullResult();

            if ($usernameAlreadyExist) {
                return $this->createErrorResponse([ErrorCode::V1_USER_USERNAME_ALREADY_EXISTS]);
            }

            $user->username = $username;
        }

        if ($patchProfileRequest->name !== null) {
            $user->name = trim($patchProfileRequest->name);
        }

        if ($patchProfileRequest->surname !== null) {
            $user->surname = trim($patchProfileRequest->surname);
        }

        if ($patchProfileRequest->about !== null) {
            $user->about = $patchProfileRequest->about;
        }

        if ($avatarId = (int) $patchProfileRequest->avatar) {
            $avatar = $userPhotoRepository->find($avatarId);

            if ($avatar && (!$user->avatar || $user->avatar->id != $avatarId)) {
                $user->avatar = $avatar;

                try {
                    $bus->dispatch(new CheckAvatarPhotoTheHiveAiMessage(
                        $avatarId,
                        $avatar->getOriginalUrl(),
                        $user->id
                    ));
                } catch (Throwable $exception) {
                    $logger->error($exception, ['exception' => $exception]);
                }
            }
        }

        $industries = $patchProfileRequest->industries;
        $skills = $patchProfileRequest->skills;
        $goals = $patchProfileRequest->goals;

        if ($industries !== null) {
            $user->industries->clear();
            $ids = array_map(fn(ReferenceResponse $r) => $r->id, $industries);
            $industries = $industryRepository->findBy(['id' => $ids]);
            $matchingData = [];
            foreach ($industries as $industry) {
                $user->industries->add($industry);
                $matchingData[] = ['industryId' => $industry->id->toString(), 'industryName' => $industry->name];
            }
            $matchingClient->publishEventOwnedBy('userIndustriesAmend', $user, ['industries' => $matchingData]);
        }

        if ($skills !== null) {
            $user->skills->clear();
            $ids = array_map(fn(ReferenceResponse $r) => $r->id, $skills);
            $skills = $skillRepository->findBy(['id' => $ids]);
            $matchingData = [];
            foreach ($skills as $skill) {
                $user->skills->add($skill);
                $matchingData[] = ['skillId' => $skill->id->toString(), 'skillName' => $skill->name];
            }
            $matchingClient->publishEventOwnedBy('userSkillsAmend', $user, ['skills' => $matchingData]);
        }

        if ($goals !== null) {
            $user->goals->clear();
            $ids = array_map(fn(ReferenceResponse $r) => $r->id, $goals);
            $goals = $goalRepository->findBy(['id' => $ids]);
            $matchingData = [];
            foreach ($goals as $goal) {
                $user->goals->add($goal);
                $matchingData[] = ['goalId' => $goal->id->toString(), 'goalName' => $goal->name];
            }
            $matchingClient->publishEventOwnedBy('userGoalsAmend', $user, ['goals' => $matchingData]);
        }

        $interests = $patchProfileRequest->interests;
        if ($interests !== null) {
            $ids = array_map(fn(InterestDTO $interestDTO) => $interestDTO->id, $interests);
            $user->clearInterests();

            if ($ids) {
                $interestsData = [];
                foreach ($interestRepository->findByIds($ids, false) as $interest) {
                    $user->addInterest($interest);
                    $interestsData[] = [
                        'groupId' => $interest->group->id ?? 0,
                        'interestId' => $interest->id,
                        'groupName' => $interest->group->name ?? '',
                        'interestName' => $interest->name,
                    ];
                }
                $matchingClient->publishEventOwnedBy('userGIModified', $user, ['interests' => $interestsData]);
            }
        }

        if ($languageId = $patchProfileRequest->languageId) {
            $language = $languageRepository->find($languageId);

            if (!$language) {
                return $this->createErrorResponse(ErrorCode::V1_LANGUAGE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $user->nativeLanguages->clear();
            $user->addNativeLanguage($language);
        }

        if ($patchProfileRequest->languages !== null) {
            $languages = $patchProfileRequest->languages;
            $languageIds = array_map(fn(LanguageDTO $language) => $language->id, $languages);
            $languageEntities = $languageRepository->findBy(['id' => $languageIds]);

            $user->nativeLanguages->clear();
            foreach ($languageEntities as $language) {
                $user->addNativeLanguage($language);
            }
        }

        if ($patchProfileRequest->linkedin !== null) {
            $user->linkedin = $patchProfileRequest->linkedin ?? null;
        }

        if ($patchProfileRequest->instagram !== null) {
            $user->instagram = $patchProfileRequest->instagram ?? null;
        }

        if ($patchProfileRequest->twitter !== null) {
            $user->twitter = $patchProfileRequest->twitter ?? null;
        }

        if ($bio = $patchProfileRequest->bio) {
            $user->longBio = $bio;
        }

        $skipNotificationUntil = $patchProfileRequest->skipNotificationUntil;
        if ($skipNotificationUntil !== null) {
            $user->skipNotificationUntil = $skipNotificationUntil > 0 ? $skipNotificationUntil : null;
        }

        $userRepository->save($user);

        $bus->dispatch(new UploadUserToElasticsearchMessage($user));
        $bus->dispatch(new SyncWithIntercomMessage($user));

        $matchingClient->publishEvent('userModified', $user);

        // Autoinvite
        if ($use_autoinvite) {
            $bot_user = $userRepository->findUserByUsername('connectclubbot');
            if ($bot_user) {
                try {
                    $inviteManager->createInviteForUser($bot_user, $user)->run();
                    $this->verify($userRepository, $bus);
                } catch (NoFreeInvitesException $exception) {
                    $logger->warning(json_encode($exception));
                }
            }
        }

        return $this->handleResponse(new CurrentUserResponse($user));
    }

    /**
     * @SWG\Post(
     *     produces={"application/json"},
     *     tags={"Account"},
     *     deprecated=true,
     *     summary="Complete registration, change state to verify",
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Response(response=422, description="Validation errors"),
     * )
     * @Security("not(is_granted('ROLE_USER_OLD'))")
     * @Route("/verify", methods={"POST"})
     */
    public function verify(UserRepository $userRepository, MessageBusInterface $bus): JsonResponse
    {
        $user = $this->getUser();

        if ($user->state !== User::STATE_INVITED) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_PRECONDITION_FAILED);
        }

        $user->state = User::STATE_VERIFIED;
        $userRepository->save($user);

        $bus->dispatch(new UploadUserToElasticsearchMessage($user));

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Patch(
     *     produces={"application/json"},
     *     tags={"Account"},
     *     description="Change user state",
     *     summary="Change user state",
     *     @SWG\Parameter(
     *      name="state", in="path", type="string", enum={User::STATE_VERIFIED, User::STATE_WAITING_LIST}
     *     ),
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Response(response=422, description="Validation errors"),
     * )
     * @Security("not(is_granted('ROLE_USER_OLD'))")
     * @Route("/{state}/state", methods={"PATCH"})
     */
    public function changeState(
        UserRepository $userRepository,
        string $state,
        MessageBusInterface $bus,
        MatchingClient $matchingClient,
        PhoneContactRepository $phoneContactRepository,
        NotificationManager $notificationManager,
        ActivityManager $activityManager,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ): JsonResponse {
        $user = $this->getUser();

        $availableStates = [
            User::STATE_VERIFIED,
            User::STATE_WAITING_LIST
        ];

        if (!in_array($state, $availableStates)) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $currentState = $user->state;
        switch ($state) {
            case User::STATE_VERIFIED:
                //@todo need move to event subscriber
                if ($currentState != User::STATE_INVITED) {
                    return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_BAD_REQUEST);
                }

                $contactOwners = [];
                if ($user->phone) {
                    $contactOwners = new ArrayCollection(
                        $phoneContactRepository
                            ->findContactOwnersWithPhoneNumber($user->phone)
                    );
                    $contactOwners = $contactOwners->filter(fn(User $owner) => !$owner->equals($user))->getValues();
                }

                foreach ($contactOwners as $contactOwner) {
                    $entityManager->persist(new UserRegisteredActivity($contactOwner, $user));
                }

                if ($user->badges === null) {
                    $user->badges = [];
                }

                $user->badges[] = 'new';
                $user->deleteNewBadgeAt = strtotime('+1 week', time());

                $this->createOnBoardingRoom($user);

                $entityManager->flush();

                $needUpdateContactsWithMe = [];
                if ($user->phone) {
                    $needUpdateContactsWithMe = $phoneContactRepository->findRegisteredContactsWhenContainsPhoneNumber(
                        PhoneNumberUtil::getInstance()->format($user->phone, PhoneNumberFormat::E164)
                    );
                }
                foreach ($needUpdateContactsWithMe as $ownerId => $userIdsFromContacts) {
                    $matchingClient->publishEventOwnedById(
                        'userContactsUpdated',
                        $ownerId,
                        ['userIds' => $userIdsFromContacts]
                    );
                }

                $bus->dispatch(new UploadUserToElasticsearchMessage($user));
                break;
            case User::STATE_WAITING_LIST:
                if ($currentState != User::STATE_NOT_INVITED) {
                    return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_BAD_REQUEST);
                }

                //@todo refactor
                if ($user->state != User::STATE_VERIFIED) {
                    $phoneNumber = $user->phone;
                    $contactOwners = $phoneNumber ? $phoneContactRepository->findContactOwnersWithPhoneNumber(
                        $phoneNumber
                    ) : [];

                    foreach ($contactOwners as $contactOwner) {
                        $activity = new NewUserFromWaitingListActivity($user->phone, $contactOwner, $user);

                        $notificationManager->sendNotifications($contactOwner, new ReactNativePushNotification(
                            Activity::TYPE_NEW_USER_ASK_INVITE,
                            $activityManager->getActivityTitle($activity),
                            $activityManager->getActivityDescription($activity),
                            [
                                'phone' => PhoneNumberUtil::getInstance()->format(
                                    $phoneNumber,
                                    PhoneNumberFormat::E164
                                ),
                                PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                                PushNotification::PARAMETER_SPECIFIC_KEY => 'new-user-from-waiting-activity',
                                PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300),
                                'userId' => (string) $user->id
                            ]
                        ));

                        $entityManager->persist($activity);
                    }
                }
                $entityManager->flush();
                break;
        }
        $user->state = $state;
        $userRepository->save($user);

        $eventDispatcher->dispatch(new ChangeStateUserEvent($user));

        $matchingClient->publishEvent('userModified', $user);

        if (!$user->isTester) {
            $message = new AmplitudeEventStatisticsMessage('api.change_state', [], $user);
            $message->userOptions['state'] = $state;

            $bus->dispatch($message);
        }

        return $this->handleResponse([]);
    }

    private function createOnBoardingRoom(User $user): void
    {
        $ignoreUser = null;
        if ($user->invite && $user->invite->club !== null) {
            $ignoreUser = $user->invite->author;
        }

        $entityManager = $this->entityManager;
        $phoneContactRepository = $this->phoneContactRepository;

        $phoneNumber = $user->phone;
        //TODO move to event and event subscriber
        if ($user->onBoardingNotificationAlreadySend || !$phoneNumber) {
            return;
        }

        $contactOwners = $phoneContactRepository->findContactOwnersWithPhoneNumber($phoneNumber);
        if (!$contactOwners) {
            return;
        }

        $contactOwner = $contactOwners[0];
        $videoRoom = $this->videoRoomManager->createVideoRoomByType(
            EventDraft::TYPE_SMALL_BROADCASTING,
            $contactOwner,
            'Welcome '.$user->name ?? $user->username ?? $user->id
        );
        $videoRoom->isPrivate = true;
        $videoRoom->addInvitedUser($user);
        $videoRoom->forPersonallyOnBoarding = $user;

        $entityManager->persist($videoRoom);

        foreach ($contactOwners as $i => $contactOwner) {
            if ($ignoreUser && $contactOwner->equals($ignoreUser)) {
                continue;
            }

            $videoRoom->addInvitedUser($contactOwner);

            $entityManager->persist(
                new CommunityParticipant($contactOwner, $videoRoom->community, CommunityParticipant::ROLE_ADMIN)
            );

            $activity = new WelcomeOnBoardingFriendActivity($videoRoom, $contactOwner, $user);
            $entityManager->persist($activity);

            $this->notificationManager->sendNotifications(
                $contactOwner,
                new ReactNativeVideoRoomNotification(
                    $videoRoom,
                    $this->activityManager->getActivityTitle($activity),
                    $this->activityManager->getActivityDescription($activity),
                    [
                        PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => 'welcome-on-boarding-friend',
                        PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(250, 250)
                    ],
                    'join-the-room'
                )
            );

            if ($i % 500 === 0) {
                $entityManager->flush();
            }
        }

        $user->onBoardingNotificationAlreadySend = true;
        $entityManager->persist($user);
    }
}
