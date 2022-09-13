<?php


namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\Jitsi\TokenResponse;
use App\Service\JitsiEndpointManager;
use App\Swagger\ViewResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jitsi")
 */
class JitsiController extends BaseController
{
    /**
     * @SWG\Post(
     *     description="Get token for jitsi",
     *     summary="Get token for jitsi",
     *     tags={"Jitsi"},
     *     @SWG\Response(response="200", description="Success response")
     * )
     * @ViewResponse(entityClass=TokenResponse::class)
     * @Route("/token/{conferenceGid}/{endpoint}", methods={"POST"})
     */
    public function token(
        string $conferenceGid,
        string $endpoint,
        JitsiEndpointManager $jitsiEndpointManager
    ) {
        $token = $jitsiEndpointManager->generateJWTTokenFor($conferenceGid, $endpoint);

        return $this->handleResponse(new TokenResponse((string) $token));
    }
}
