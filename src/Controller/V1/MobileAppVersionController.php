<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\MobileAppVersionResponse;
use App\DTO\V1\MobileAppVersionWithConfigResponse;
use App\Repository\MobileAppConfigRepository;
use App\Repository\MobileAppVersionRepository;
use App\Swagger\ViewResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/mobile-app-version")
 */
class MobileAppVersionController extends BaseController
{
    /**
     * @SWG\Get(
     *     tags={"System"},
     *     summary="Get actual mobile app version for platform",
     *     description="Get actual mobile app version for platform",
     *     @SWG\Parameter(
     *         in="query",
     *         enum={"ios", "android", "desktop"},
     *         type="string",
     *         name="platform",
     *         description="Platform code (ios, android)"
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         enum={"1", "2"},
     *         type="string",
     *         name="version",
     *         description="Version"
     *     ),
     *     @SWG\Response(response="200", description="Version information"),
     *     @SWG\Response(response="400", description="Incorrrect platform code"),
     *     @SWG\Response(response="404", description="Not found version for platform")
     * )
     * @ViewResponse(entityClass=MobileAppVersionResponse::class)
     * @Route("", methods={"GET"})
     */
    public function version(
        MobileAppVersionRepository $mobileAppVersionRepository,
        MobileAppConfigRepository $mobileAppConfigRepository,
        Request $request
    ) {
        $platform = $request->query->get('platform');
        if (!in_array($platform, ['ios', 'android', 'desktop'])) {
            return $this->createErrorResponse([ErrorCode::V1_MOBILE_VERSION_PLATFORM_NOT_FOUND], 400);
        }

        $mobileAppVersion = $mobileAppVersionRepository->findActuallyVersionForPlatform($platform);
        if (!$mobileAppVersion) {
            return $this->createErrorResponse([ErrorCode::V1_MOBILE_VERSION_NOT_FOUND], 404);
        }

        switch ($request->query->getInt('version')) {
            case 2:
                $onboarding = false;
                if ($config = $mobileAppConfigRepository->findOneBy(['platform' => $platform])) {
                    $onboarding = $config->onboarding;
                }
                $response = new MobileAppVersionWithConfigResponse($mobileAppVersion->version, $onboarding);
                break;
            case 1:
            default:
                $response = new MobileAppVersionResponse($mobileAppVersion->version);
                break;
        }

        return $this->handleResponse($response);
    }
}
