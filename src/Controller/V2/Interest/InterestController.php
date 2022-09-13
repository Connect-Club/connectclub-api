<?php

namespace App\Controller\V2\Interest;

use App\Controller\BaseController;
use App\DTO\V2\Interests\InterestGroupResponse;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Repository\Interest\InterestGroupRepository;
use App\Repository\Interest\InterestRepository;
use App\Swagger\ListResponse;
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
    private InterestGroupRepository $interestGroupRepository;

    public function __construct(
        InterestRepository $interestRepository,
        InterestGroupRepository $interestGroupRepository
    ) {
        $this->interestRepository = $interestRepository;
        $this->interestGroupRepository = $interestGroupRepository;
    }

    /**
     * @SWG\Get(
     *     description="List all interests",
     *     summary="List all interests",
     *     tags={"Interests"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Parameter(
     *        in="query",
     *        name="withLanguages",
     *        type="boolean",
     *        description="Show languages?",
     *        default="true",
     *        required=false
     *     )
     * )
     * @ListResponse(entityClass=InterestGroupResponse::class)
     * @Route("", methods={"GET"})
     */
    public function interests(): JsonResponse
    {
        $interestGroups = $this->interestGroupRepository->findBy(['isOld' => true], ['globalSort' => 'DESC']);

        $interestGroups = array_map(fn(InterestGroup $group) => new InterestGroupResponse($group), $interestGroups);

        return $this->handleResponse(array_values($interestGroups));
    }
}
