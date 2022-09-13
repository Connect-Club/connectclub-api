<?php

namespace App\Controller\V1;

use OAuth2\OAuth2;
use OAuth2\OAuth2ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class TokenController extends AbstractController
{
    private OAuth2 $server;
    private LoggerInterface $logger;

    public function __construct(OAuth2 $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    public function tokenAction(Request $request)
    {
        try {
            return $this->server->grantAccessToken($request);
        } catch (OAuth2ServerException $e) {
            $this->logger->warning((string) $e, ['exception' => $e]);

            return $e->getHttpResponse();
        }
    }
}
