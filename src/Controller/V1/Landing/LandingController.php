<?php

namespace App\Controller\V1\Landing;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Landing\CreateLandingRequest;
use App\DTO\V1\Landing\LandingInfoResponse;
use App\DTO\V1\Landing\LandingWithParamsResponse;
use App\DTO\V1\PaginatedResponse;
use App\Entity\Landing\Landing;
use App\Repository\Landing\LandingRepository;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Nelmio\ApiDocBundle\Annotation\Model;
use Ramsey\Uuid\Uuid;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/landing")
 */
class LandingController extends BaseController
{
    private LandingRepository $landingRepository;

    public function __construct(LandingRepository $landingRepository)
    {
        $this->landingRepository = $landingRepository;
    }

    /**
     * @SWG\Post(
     *     description="Create landing",
     *     summary="Create landing",
     *     tags={"Landing"},
     *     @SWG\Parameter(name="body", in="body", @SWG\Schema(ref=@Model(type=CreateLandingRequest::class))),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=LandingWithParamsResponse::class)
     * @Route("", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        /** @var CreateLandingRequest $createLandingRequest */
        $createLandingRequest = $this->getEntityFromRequestTo($request, CreateLandingRequest::class);
        if ($this->landingRepository->findOneBy(['url' => $createLandingRequest->url])) {
            return $this->createErrorResponse(ErrorCode::V1_LANDING_URL_ALREADY_RESERVED, Response::HTTP_CONFLICT);
        }

        $landing = new Landing(
            $this->getUser(),
            $createLandingRequest->name ?? '',
            $createLandingRequest->status ?? Landing::STATUS_HIDE,
            $createLandingRequest->url ?? '',
            $createLandingRequest->title ?? '',
            $createLandingRequest->params ?? [],
            $createLandingRequest->subtitle
        );

        if (!$createLandingRequest->url) {
            $landing->url = $landing->id->toString();
        }

        $this->landingRepository->save($landing);

        return $this->handleResponse(new LandingWithParamsResponse($landing));
    }

    /**
     * @SWG\Patch(
     *     description="Update landing",
     *     summary="Update landing",
     *     tags={"Landing"},
     *     @SWG\Parameter(name="body", in="body", @SWG\Schema(ref=@Model(type=CreateLandingRequest::class))),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=LandingWithParamsResponse::class)
     * @Route("/{id}", methods={"PATCH"})
     */
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var CreateLandingRequest $updateLandingRequest */
        $updateLandingRequest = $this->getEntityFromRequestTo($request, CreateLandingRequest::class);

        $landing = $this->landingRepository->find($id);
        if (!$landing) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if ($updateLandingRequest->name !== null) {
            $landing->name = $updateLandingRequest->name;
        }

        if ($updateLandingRequest->subtitle !== null) {
            $landing->subtitle = $updateLandingRequest->subtitle;
        }

        if ($updateLandingRequest->title !== null) {
            $landing->title = $updateLandingRequest->title;
        }

        if ($updateLandingRequest->url !== null) {
            $landing->url = $updateLandingRequest->url;

            if ($landingDuplicate = $this->landingRepository->findOneBy(['url' => $updateLandingRequest->url])) {
                if (!$landingDuplicate->id->equals($landing->id)) {
                    return $this->createErrorResponse(
                        ErrorCode::V1_LANDING_URL_ALREADY_RESERVED,
                        Response::HTTP_CONFLICT
                    );
                }
            }
        }

        if ($updateLandingRequest->status !== null) {
            $landing->status = $updateLandingRequest->status;
        }

        foreach ($updateLandingRequest->params as $key => $value) {
            if ($value === null && isset($landing->params[$key])) {
                unset($landing->params[$key]);
            } else {
                $landing->params[$key] = $value;
            }
        }

        $this->landingRepository->save($landing);

        return $this->handleResponse(new LandingWithParamsResponse($landing));
    }

    /**
     * @SWG\Get(
     *     description="Get all landings",
     *     summary="Get all landings",
     *     tags={"Landing"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=LandingInfoResponse::class,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("", methods={"GET"})
     */
    public function all(): JsonResponse
    {
        $items = array_map(
            fn(Landing $l) => new LandingInfoResponse($l),
            $this->landingRepository->findAll()
        );

        return $this->handleResponse(new PaginatedResponse($items, null));
    }

    /**
     * @SWG\Get(
     *     description="Get landing detailed info",
     *     summary="Get landing detailed info",
     *     tags={"Landing"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=LandingWithParamsResponse::class)
     * @Route("/{idOrUrl}", methods={"GET"})
     */
    public function item(string $idOrUrl): JsonResponse
    {
        if (Uuid::isValid($idOrUrl)) {
            $landing = $this->landingRepository->find($idOrUrl);
        } else {
            $landing = $this->landingRepository->findOneBy(['url' => $idOrUrl]);
        }

        if (!$landing) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse(new LandingWithParamsResponse($landing));
    }
}
