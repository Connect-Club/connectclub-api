<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\InstallationStatisticRequest;
use App\Entity\Statistic\Installation;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\Statistic\InstallationRepository;
use MaxMind\Db\Reader;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * @Route("/statistics")
 */
class StatisticController extends BaseController
{
    /**
     * @SWG\Post(
     *     produces={"application/json"},
     *     tags={"Statistic"},
     *     summary="Installation statistic track",
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Response(response=400, description="Validation errors"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         @SWG\Schema(ref=@Model(type=InstallationStatisticRequest::class))
     *     )
     * )
     * @Route("/installation", methods={"POST"})
     */
    public function installation(
        Request $request,
        InstallationRepository $installationRepository,
        Reader $reader,
        MessageBusInterface $bus
    ): JsonResponse {
        /** @var InstallationStatisticRequest $installationRequest */
        $installationRequest = $this->getEntityFromRequestTo($request, InstallationStatisticRequest::class);

        if ($installation = $installationRepository->findOneBy(['deviceId' => $installationRequest->deviceId])) {
            $installation->isFirstInstall = false;
            $installationRepository->save($installation);

            $bus->dispatch(new AmplitudeEventStatisticsMessage(
                'api.user.reinstall',
                ['utm_campaign' => $installationRequest->utm],
                null,
                $installationRequest->deviceId
            ));

            return $this->handleResponse([]);
        }

        $ip = $request->getClientIp() ?? '';

        $detectedRegionCode = null;
        try {
            $detectedRegionCode = $reader->get($ip)['country']['iso_code'] ?? null;
        } catch (Throwable $e) {
        }

        $installation = new Installation($installationRequest->deviceId, $ip, $detectedRegionCode);
        $installation->platform = $installationRequest->platform;
        $installation->utm = $installationRequest->utm;

        $installationRepository->save($installation);

        $bus->dispatch(new AmplitudeEventStatisticsMessage(
            'api.user.first_install',
            ['utm_campaign' => $installationRequest->utm],
            null,
            $installationRequest->deviceId
        ));

        return $this->handleResponse([]);
    }
}
