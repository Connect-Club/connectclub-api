<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\PostDeviceRequest;
use App\DTO\V1\User\CurrentUserResponse;
use App\DTO\V1\User\UserResponse;
use App\DTO\V1\User\AccountPatchProfileRequest;
use App\Entity\OAuth\AccessToken;
use App\Entity\User;
use App\Event\User\DeleteAccountEvent;
use App\Message\CheckAvatarPhotoTheHiveAiMessage;
use App\OAuth2\OAuth2;
use App\Repository\Interest\InterestRepository;
use App\Repository\Location\CityRepository;
use App\Repository\Location\CountryRepository;
use App\Repository\OAuth\AccessTokenRepository;
use App\Repository\Photo\UserPhotoRepository;
use App\Repository\User\DeviceRepository;
use App\Repository\UserRepository;
use App\Service\LocationManager;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use libphonenumber\PhoneNumberUtil;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Throwable;

/**
 * Class AccountController.
 *
 * @Route("/account")
 */
class AccountController extends BaseController
{
    /**
     * @SWG\Delete(
     *     produces={"application/json"},
     *     tags={"Account"},
     *     summary="Delete current account",
     *     description="Delete current account",
     *     @SWG\Response(response=200, description="Success response"),
     * )
     * @ViewResponse()
     * @Route("", methods={"DELETE"})
     */
    public function delete(EntityManagerInterface $entityManager, EventDispatcherInterface $dispatcher)
    {
        $currentUser = $this->getUser();

        $dispatcher->dispatch(new DeleteAccountEvent($currentUser));

        $entityManager->remove($currentUser);
        $entityManager->flush();

        return $this->handleResponse([]);
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
     * @Route("/current", methods={"GET"})
     */
    public function current()
    {
        $currentUser = $this->getUser();

        $response = new CurrentUserResponse($currentUser);

        return $this->handleResponse($response, Response::HTTP_OK);
    }

    /**
     * @SWG\Post(
     *     produces={"application/json"},
     *     tags={"Account"},
     *     security={{"oauth2BearerToken":{}}},
     *     summary="Terminate current user session",
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         schema=@SWG\Schema(ref=@Nelmio\Model(type=PostDeviceRequest::class))
     *     )
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("/logout", methods={"POST"})
     */
    public function logout(
        Request $request,
        TokenStorageInterface $tokenStorage,
        AccessTokenRepository $accessTokenRepository,
        DeviceRepository $deviceRepository,
        EntityManagerInterface $entityManager
    ) {
        /** @var OAuthToken $token */
        $token = $tokenStorage->getToken();

        /** @var PostDeviceRequest $postDeviceRequest */
        $postDeviceRequest = $this->getEntityFromRequestTo($request, PostDeviceRequest::class);

        $accessToken = $accessTokenRepository->findOneBy(['token' => $token->getToken()]);
        if ($accessToken) {
            $entityManager->remove($accessToken);
        }

        $userId = $this->getUser()->getId();
        if ($deviceId = $postDeviceRequest->deviceId) {
            if ($device = $deviceRepository->find($userId.'_'.$deviceId)) {
                $entityManager->remove($device);
            }
        }

        $entityManager->flush();

        return $this->handleResponse([]);
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
     * @ViewResponse(entityClass=User::class, groups={"v1.account.current"})
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("/profile", methods={"PATCH"})
     */
    public function updateProfile(
        Request $request,
        UserRepository $userRepository,
        UserPhotoRepository $userPhotoRepository,
        CityRepository $cityRepository,
        InterestRepository $interestRepository,
        CountryRepository $countryRepository,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        /** @var AccountPatchProfileRequest|iterable $patchProfileRequest */
        $patchProfileRequest = $this->getEntityFromRequestTo($request, AccountPatchProfileRequest::class);

        foreach (['name', 'surname', 'company', 'position', 'phone', 'about'] as $field) {
            $patchProfileRequest->{$field} = trim(preg_replace(
                '/[^[:print:][:space:]]/u',
                ' ',
                $patchProfileRequest->{$field}
            ));
        }

        $this->unprocessableUnlessValid($patchProfileRequest);

        $user = $this->getUser();

        $user->name = $patchProfileRequest->name;
        $user->surname = $patchProfileRequest->surname;
        $user->about = $patchProfileRequest->about;

        if ($avatarId = (int) $patchProfileRequest->avatar) {
            $avatar = $userPhotoRepository->find($avatarId);

            if ($avatar) {
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

        if ($country = $patchProfileRequest->country) {
            $user->country = $countryRepository->find($country->id);
        }

        if ($city = $patchProfileRequest->city) {
            if ($cityEntity = $cityRepository->find($city->id)) {
                $user->city = $cityEntity;
                $user->country = $cityEntity->country;
            }
        }

        if ($interests = $patchProfileRequest->interests) {
            $ids = array_map(fn(InterestDTO $interestDTO) => $interestDTO->id, $interests);
            $user->clearInterests();

            foreach ($interestRepository->findByIds($ids) as $interest) {
                $user->addInterest($interest);
            }
        }

        $userRepository->save($user);

        return $this->handleResponse(new CurrentUserResponse($user), Response::HTTP_OK);
    }

    /**
     * @Route("/expires-in", methods={"POST"})
     */
    public function expiresIn(
        Request $request,
        \OAuth2\OAuth2 $auth2,
        AccessTokenRepository $accessTokenRepository
    ): JsonResponse {
        $authorization = $auth2->getBearerToken($request);

        /** @var AccessToken $accessToken */
        $accessToken = $accessTokenRepository->findOneBy(['token' => $authorization]);

        $accessToken->setExpiresAt(time() - 3600);
        $accessTokenRepository->save($accessToken);

        return $this->handleResponse([]);
    }
}
