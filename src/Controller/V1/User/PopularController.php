<?php

namespace App\Controller\V1\User;

use App\Controller\BaseController;
use App\DTO\V1\User\PopularUserItemResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Swagger\ListResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/popular")
 */
class PopularController extends BaseController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get 100 popular users by invites",
     *     summary="Get 100 poplular users by invites",
     *     security=false,
     *     tags={"User"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=PopularUserItemResponse::class)
     * @Route("/inviters", methods={"GET"})
     */
    public function popular(): JsonResponse
    {
        $popularUsers = $this->userRepository->findMostPopularUsersByInvites();

        $response = array_map(
            fn(array $userInfo) => new PopularUserItemResponse($userInfo['user'], $userInfo['count']),
            $popularUsers
        );

        return $this->handleResponse($response);
    }
}
