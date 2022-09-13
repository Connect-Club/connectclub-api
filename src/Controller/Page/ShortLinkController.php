<?php

namespace App\Controller\Page;

use App\ConnectClub;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Repository\VideoRoom\ScreenShareTokenRepository;
use DateTimeImmutable;
use DeviceDetector\DeviceDetector;
use Exception;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s")
 */
class ShortLinkController extends BaseController
{
    /**
     * @Route("/{token}", name="v1_short_link")
     */
    public function index(
        Request $request,
        ScreenShareTokenRepository $tokenRepository,
        string $token
    ): Response {
        $token = $tokenRepository->findOneBy(['token' => $token]);
        if (!$token) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $time = new DateTimeImmutable();

        $token = (new Builder())
            ->issuedAt($time)
            ->canOnlyBeUsedAfter($time)
            ->expiresAt($time->modify('+'.ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT.' seconds'))
            ->withClaim('endpoint', 'screen-'.$token->user->id)
            ->withClaim('conferenceGid', $token->videoRoom->community->name)
            ->getToken(new Sha256(), new Key($_ENV['JWT_TOKEN_PRIVATE_KEY']));

        $browserName = $request->server->get('HTTP_USER_AGENT');

        $detectedBrowserName = null;
        try {
            $deviceDetector = new DeviceDetector($browserName ?? '');
            $deviceDetector->parse();
            $detectedBrowserName = $deviceDetector->getClient('name');
        } catch (Exception $exception) {
        }

        if ($detectedBrowserName !== 'Chrome') {
            return $this->render('sharingScreenDisableBrowser.html.twig', [
                'browserName' => $browserName,
            ]);
        }

        return $this->render('sharingScreen.html.html.twig', [
            'token' => $token,
            'jitsiAddress' => $_ENV['JITSI_SERVER'],
            'browserName' => $browserName,
        ]);
    }
}
