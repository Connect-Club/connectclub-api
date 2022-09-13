<?php

namespace App\Controller\V1\Ethereum;

use App\Controller\BaseController;
use App\DTO\V1\PaginatedResponseWithCount;
use App\Service\MatchingClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nft")
 */
class NftTokenController extends BaseController
{
    /** @Route("/tokens", methods={"GET"}) */
    public function nft(Request $request, MatchingClient $matchingClient): JsonResponse
    {
        $lastValue = $request->query->getInt('lastValue');
        $limit = $request->query->getInt('limit');

        $data = $matchingClient->findTokens($limit, $lastValue)['data'];

        return $this->handleResponse(
            new PaginatedResponseWithCount($data['items'], $data['lastValue'], $data['totalValue'])
        );
    }
}
