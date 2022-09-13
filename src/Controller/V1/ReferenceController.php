<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\Reference\ReferenceResponse;
use App\DTO\V1\Reference\SkillCategoryResponse;
use App\Entity\Matching\Goal;
use App\Entity\Matching\Industry;
use App\Entity\Matching\Skill;
use App\Repository\Matching\GoalRepository;
use App\Repository\Matching\IndustryRepository;
use App\Repository\Matching\SkillCategoryRepository;
use App\Repository\Matching\SkillRepository;
use App\Swagger\ListResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/** @Route("/reference") */
class ReferenceController extends BaseController
{
    private SkillRepository $skillRepository;
    private SkillCategoryRepository $skillCategoryRepository;
    private IndustryRepository $industryRepository;
    private GoalRepository $goalRepository;

    public function __construct(
        SkillRepository $skillRepository,
        SkillCategoryRepository $skillCategoryRepository,
        IndustryRepository $industryRepository,
        GoalRepository $goalRepository
    ) {
        $this->skillRepository = $skillRepository;
        $this->skillCategoryRepository = $skillCategoryRepository;
        $this->industryRepository = $industryRepository;
        $this->goalRepository = $goalRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get all skills",
     *     summary="Get all skills",
     *     tags={"References"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=SkillCategoryResponse::class)
     * @Route("/skills", methods={"GET"})
     */
    public function skills(): JsonResponse
    {
        $skills = $this->skillRepository
            ->createQueryBuilder('s')
            ->addSelect('c')
            ->join('s.category', 'c')
            ->getQuery()
            ->getResult();

        $skillsCategories = $this->skillCategoryRepository->findAll();

        $response = [];
        foreach ($skillsCategories as $skillCategory) {
            $skillsOfCategory = array_filter(
                $skills,
                fn(Skill $skill) => $skill->category->id->equals($skillCategory->id)
            );

            $response[] = new SkillCategoryResponse($skillCategory, array_values($skillsOfCategory));
        }

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Get(
     *     description="Get all goals",
     *     summary="Get all goals",
     *     tags={"References"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=ReferenceResponse::class)
     * @Route("/goals", methods={"GET"})
     */
    public function goals(): JsonResponse
    {
        $response = array_map(fn(Goal $g) => new ReferenceResponse($g), $this->goalRepository->findAll());

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Get(
     *     description="Get all industries",
     *     summary="Get all industries",
     *     tags={"References"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=ReferenceResponse::class)
     * @Route("/industries", methods={"GET"})
     */
    public function industries(): JsonResponse
    {
        $response = array_map(fn(Industry $g) => new ReferenceResponse($g), $this->industryRepository->findAll());

        return $this->handleResponse($response);
    }
}
