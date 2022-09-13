<?php

namespace App\Controller\V1\User;

use App\ConnectClub;
use App\Controller\BaseController;
use App\DTO\V1\User\CreateAuthSignatureRequest;
use App\DTO\V1\User\CreateWalletRequest;
use App\DTO\V1\User\CreateWalletSignatureResponse;
use App\Entity\User;
use App\Exception\User\MetamaskInvalidWalletDataException;
use App\Repository\UserRepository;
use App\Service\MatchingClient;
use App\Service\MetamaskManager;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Ramsey\Uuid\Uuid;
use Redis;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user/wallet")
 */
class WalletController extends BaseController
{
    private EntityManagerInterface $entityManager;
    private LockFactory $lockFactory;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LockFactory $lockFactory,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->lockFactory = $lockFactory;
        $this->userRepository = $userRepository;
    }

    /**
     * @SWG\Post(
     *     description="Create wallet",
     *     summary="Create wallet",
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateWalletRequest::class))),
     *     @SWG\Response(response="201", description="Ok response"),
     * )
     * @ViewResponse()
     * @Route("", methods={"POST"})
     */
    public function create(
        Request $request,
        MetamaskManager $metamaskManager,
        MatchingClient $matchingClient
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->lockFactory->createLock('work_with_user_nonce_'.$currentUser->username)->acquire(true);
        $this->entityManager->refresh($currentUser);

        /** @var CreateWalletRequest $createWalletRequest */
        $createWalletRequest = $this->getEntityFromRequestTo($request, CreateWalletRequest::class);

        try {
            $check = $metamaskManager->checkMetamaskWallet(
                $currentUser->metaMaskNonce,
                $createWalletRequest->text,
                $createWalletRequest->address,
                $createWalletRequest->signature
            );

            if (!$check) {
                return $this->createErrorResponse('validation_error');
            }
        } catch (MetamaskInvalidWalletDataException $exception) {
            return $this->createErrorResponse($exception->getMessage());
        }

        $address = mb_strtolower($createWalletRequest->address);

        $userReservedWallet = $this->userRepository->findOneBy(['wallet' => $address]);
        if ($userReservedWallet && !$userReservedWallet->equals($currentUser)) {
            return $this->createErrorResponse('wallet_already_reserved');
        }

        $currentUser->metaMaskNonce = Uuid::uuid4()->toString();
        $currentUser->wallet = $address;
        $this->userRepository->save($currentUser);

        $matchingClient->publishEvent('userWalletAdded', $currentUser, ['wallet' => $currentUser->wallet]);

        return $this->handleResponse([], Response::HTTP_CREATED);
    }

    /**
     * @SWG\Post(
     *     description="Create wallet signature nonce",
     *     summary="Create wallet signature nonce",
     *     tags={"User"},
     *     @SWG\Response(response="200", description="Ok response"),
     * )
     * @ViewResponse(entityClass=CreateWalletSignatureResponse::class)
     * @Route("/signature", methods={"POST"})
     */
    public function signature(): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $this->lockFactory->createLock('work_with_user_nonce_'.$currentUser->username)->acquire(true);
        $this->entityManager->refresh($currentUser);

        if (!$currentUser->metaMaskNonce) {
            $currentUser->metaMaskNonce = Uuid::uuid4()->toString();
            $this->userRepository->save($currentUser);
        }

        $nonce = ConnectClub::generateMetamaskMessageForUser($currentUser);

        return $this->handleResponse(new CreateWalletSignatureResponse(
            $currentUser->metaMaskNonce,
            $nonce
        ));
    }

    /**
     * @SWG\Post(
     *     description="Create wallet signature nonce for authorization",
     *     summary="Create wallet signature nonce for authorization",
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateAuthSignatureRequest::class))),
     *     @SWG\Response(response="200", description="Ok response"),
     * )
     * @ViewResponse(entityClass=CreateWalletSignatureResponse::class)
     * @Route("/auth-signature", methods={"POST"})
     */
    public function authSignature(Request $request, Redis $redis, MatchingClient $matchingClient): JsonResponse
    {
        /** @var CreateAuthSignatureRequest $createAuthSignatureRequest */
        $createAuthSignatureRequest = $this->getEntityFromRequestTo($request, CreateAuthSignatureRequest::class);

        $metaMaskAuthorizationKey = $createAuthSignatureRequest->deviceId;
        if (!$metaMaskAuthorizationKey) {
            return $this->createErrorResponse('metamask_authorization_device_id_not_found');
        }

        $redisKey = 'metamask_nonce_for_'.$metaMaskAuthorizationKey;

        $nonce = $redis->get($redisKey);
        if (!$nonce) {
            $nonce = Uuid::uuid4()->toString();
            $redis->set($redisKey, $nonce);
            $redis->expire($redisKey, 3600);
        }

        return $this->handleResponse(new CreateWalletSignatureResponse(
            $nonce,
            ConnectClub::generateMetamaskMessageForNonce($nonce)
        ));
    }

    /**
     * @SWG\Delete(
     *     description="Delete wallet",
     *     summary="Delete wallet",
     *     tags={"User"},
     *     @SWG\Response(response="200", description="Ok response"),
     * )
     * @ViewResponse()
     * @Route("", methods={"DELETE"})
     */
    public function remove(MatchingClient $matchingClient): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $currentUser->wallet = null;
        $this->userRepository->save($currentUser);

        $matchingClient->publishEvent('userWalletRemoved', $currentUser);

        return $this->handleResponse([]);
    }
}
