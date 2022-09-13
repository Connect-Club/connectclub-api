<?php


namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V2\Interests\InterestGroupResponse;
use App\DTO\V2\User\LanguageDTO;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User\Language;
use App\Repository\Interest\InterestRepository;
use App\Repository\User\LanguageRepository;
use App\Swagger\ViewResponse;
use Doctrine\Common\Collections\Criteria;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/language")
 */
class LanguageController extends BaseController
{
    private LanguageRepository $languageRepository;

    public function __construct(LanguageRepository $languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get languages",
     *     summary="Get languages",
     *     tags={"Language"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=LanguageDTO::class)
     * @Route("", methods={"GET"})
     */
    public function languages(): JsonResponse
    {
        $languages = $this->languageRepository
                          ->matching(Criteria::create()->orderBy(['sort' => 'ASC']))
                          ->toArray();

        return $this->handleResponse(array_map(fn(Language $l) => new LanguageDTO($l), $languages));
    }
}
