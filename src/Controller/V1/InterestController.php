<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\Interests\InterestGroupResponse;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User;
use App\Repository\Interest\InterestGroupRepository;
use App\Repository\Interest\InterestRepository;
use App\Repository\UserRepository;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/interests")
 */
class InterestController extends BaseController
{
    private InterestRepository $interestRepository;

    public function __construct(
        InterestRepository $interestRepository
    ) {
        $this->interestRepository = $interestRepository;
    }

    /**
     * @SWG\Get(
     *     description="List all interests",
     *     summary="List all interests",
     *     tags={"Interests"},
     *     @SWG\Response(response="200", description="Success")
     * )
     * @ListResponse(entityClass=InterestDTO::class)
     * @Route("", methods={"GET"})
     */
    public function interests(): JsonResponse
    {
        $interests = array_map(
            fn(Interest $i) => new InterestDTO($i),
            $this->interestRepository->findBy(['languageCode' => null, 'isOld' => false])
        );

        return $this->handleResponse($interests);
    }
}
