<?php

namespace App\Controller\V1;

use App\Annotation\Security;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Invite\CreateInviteRequest;
use App\DTO\V1\Invite\InviteCodeResponse;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Event\User\UserInvitedEvent;
use App\Exception\NoFreeInvitesException;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\Activity\NewUserFromWaitingListActivityRepository;
use App\Repository\Activity\NewUserRegisteredByInviteCodeActivityRepository;
use App\Repository\Invite\InviteRepository;
use App\Repository\User\PhoneContactRepository;
use App\Repository\UserRepository;
use App\Service\InviteManager;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\PhoneNumberManager;
use App\Service\Transaction\TransactionManager;
use App\Service\UserService;
use App\Swagger\ViewResponse;
use App\Transaction\FlushEntityManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use Nelmio\ApiDocBundle\Annotation\Model;
use Ramsey\Uuid\Uuid;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route("/invite")
 */
class InviteController extends BaseController
{
    private InviteRepository $inviteRepository;

    public function __construct(InviteRepository $inviteRepository)
    {
        $this->inviteRepository = $inviteRepository;
    }

    /**
     * @SWG\Post(
     *     description="Create invite",
     *     summary="Create invite",
     *     tags={"Invite"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateInviteRequest::class))),
     *     @SWG\Response(response="201", description="Ok response"),
     *     @SWG\Response(response="400", description="Invite already exists"),
     * )
     * @ViewResponse(errorCodesMap={
     *     {ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES, Response::HTTP_BAD_REQUEST, "No free invites"}
     * })
     * @Security(role="ROLE_USER")
     * @Route("", methods={"POST"})
     */
    public function create(
        Request $request,
        MessageBusInterface $bus,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        TransactionManager $transactionManager,
        EventDispatcherInterface $eventDispatcher,
        NotificationManager $notificationManager,
        PhoneNumberManager $phoneNumberManager,
        LockFactory $lockFactory,
        UserService $userService
    ): JsonResponse {
        /** @var CreateInviteRequest $createInviteRequest */
        $createInviteRequest = $this->getEntityFromRequestTo($request, CreateInviteRequest::class);

        $this->unprocessableUnlessValid($createInviteRequest);

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $phoneNumberObject = $phoneNumberUtil->parse($createInviteRequest->phone, PhoneNumberUtil::UNKNOWN_REGION);

        $lock = $lockFactory->createLock('create_invite_for_'.$phoneNumberManager->formatE164($phoneNumberObject));
        $lock->acquire(true);

        $author = $this->getUser();
        if ($author->freeInvites <= 0) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES, Response::HTTP_BAD_REQUEST);
        }

        if ($this->inviteRepository->findInviteByAuthorAndPhoneNumber($author, $phoneNumberObject)) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_INVITE_ALREADY_EXISTS], Response::HTTP_CONFLICT);
        }

        $invite = new Invite($author, $phoneNumberObject);
        $user = $userRepository->findUserByPhoneNumber($phoneNumberObject);
        if ($user) {
            if ($user->invite) {
                return $this->createErrorResponse(
                    [ErrorCode::V1_ERROR_INVITE_USER_ALREADY_REGISTERED],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $invite->registeredUser = $user;
            $user->state = User::STATE_INVITED;

            if (!$user->isTester) {
                $message = new AmplitudeEventStatisticsMessage('api.change_state', [], $user);
                $message->userOptions['state'] = $user->state;
                $bus->dispatch($message);
            }

            $transactionManager
                ->addTransaction(new FlushEntityManagerTransaction($entityManager, $user))
                ->addTransaction(fn() => $eventDispatcher->dispatch(new UserInvitedEvent($user)))
            ;
        }

        $transactionManager->addTransaction(new FlushEntityManagerTransaction($entityManager, $invite));

        if (!$userService->isTester($author)) {
            $bus->dispatch(new AmplitudeEventStatisticsMessage('api.create_invite', [], $author));
        }

        $author->freeInvites -= 1;
        $transactionManager->addTransaction(new FlushEntityManagerTransaction($entityManager, $author))->run();

        if ($user) {
            $notificationManager->sendNotifications($user, new ReactNativePushNotification(
                'let-you-in',
                null,
                'notifications.let_you_in',
                [],
                ['%displayName%' => $author->getFullNameOrUsername()]
            ));
        }

        return $this->handleResponse([], Response::HTTP_CREATED);
    }

    /**
     * @SWG\Get (
     *     description="Get invite code",
     *     summary="Get invite code",
     *     tags={"Invite"},
     *     @SWG\Response(response="201", description="Ok response"),
     * )
     * @ViewResponse(entityClass=InviteCodeResponse::class)
     * @Security(role="ROLE_USER")
     * @Route("/code", methods={"GET"})
     */
    public function code(
        UserRepository $userRepository,
        LockFactory $lockFactory,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->inviteCode) {
            $lockFactory->createLock('generate_invite_link_'.$currentUser->id, 100, true)->acquire(true);
            $em->refresh($currentUser);
            $currentUser->inviteCode = $currentUser->inviteCode ?? Uuid::uuid4()->toString();
            $userRepository->save($currentUser);
        }

        return $this->handleResponse(new InviteCodeResponse($currentUser->inviteCode));
    }

    /**
     * @SWG\Post(
     *     description="Create invite by user id",
     *     summary="Create invite by user id",
     *     tags={"Invite"},
     *     @SWG\Response(response="201", description="Ok response"),
     *     @SWG\Response(response="400", description="Invite already exists"),
     * )
     * @ViewResponse(errorCodesMap={
     *     {ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES, Response::HTTP_BAD_REQUEST, "No free invites"}
     * })
     * @Security(role="ROLE_USER")
     * @Route("/{userId}", methods={"POST"})
     */
    public function createInviteByUserId(
        string $userId,
        UserRepository $userRepository,
        InviteManager $inviteManager,
        NewUserRegisteredByInviteCodeActivityRepository $activityRepository,
        NotificationManager $notificationManager
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $user = $userRepository->find((int) $userId);
        if (!$user) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            if (!$currentUser->inviteCode || $user->registerByInviteCode !== $currentUser->inviteCode) {
                return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            if (!in_array($user->state, [User::STATE_NOT_INVITED, User::STATE_WAITING_LIST])) {
                return $this->createErrorResponse('user_not_in_waiting_list', Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $inviteManager->createInviteForUser($currentUser, $user)->run();
        } catch (NoFreeInvitesException $exception) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES, Response::HTTP_BAD_REQUEST);
        }

        $activityRepository->removeWithUser($currentUser, $user);

        $notificationManager->sendNotifications($user, new ReactNativePushNotification(
            'let-you-in',
            null,
            'notifications.let_you_in',
            [],
            ['%displayName%' => $currentUser->getFullNameOrUsername()]
        ));

        return $this->handleResponse([]);
    }
}
