<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Swagger\Annotations as SWG;
use Symfony\Component\Routing\Annotation\Route;

class StatusController extends BaseController
{
    /**
     * @SWG\Get(
     *     tags={"System"},
     *     @SWG\Response(response="200", description="System status OK"),
     *     description="Status system",
     *     summary="Status system"
     * )
     * @ViewResponse(security=false)
     * @Route("/status", methods={"GET"})
     */
    public function index(EntityManagerInterface $entityManager)
    {
        $entityManager->createNativeQuery('SELECT 1', new ResultSetMapping())->getResult();

        return $this->handleResponse([]);
    }
}
