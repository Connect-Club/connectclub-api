<?php

namespace App\Controller\V1\Ethereum;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Club\ClubResponse;
use App\DTO\V1\Ethereum\CreateTokenRequest;
use App\DTO\V1\Ethereum\SlimTokenResponse;
use App\DTO\V1\Ethereum\TokenResponse;
use App\DTO\V1\Ethereum\UserTokenResponse;
use App\DTO\V1\PaginatedResponseWithCount;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\ClubToken;
use App\Entity\Ethereum\Token;
use App\Entity\Ethereum\UserToken;
use App\Entity\User;
use App\Repository\Club\ClubRepository;
use App\Repository\Club\ClubTokenRepository;
use App\Repository\Ethereum\TokenRepository;
use App\Repository\Ethereum\UserTokenRepository;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Ethereum\DataType\EthD;
use Ethereum\DataType\EthQ;
use Ethereum\Ethereum;
use Ethereum\SmartContract;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * @Route("/smart-contract")
 */
class SmartContractController extends BaseController
{
    private TokenRepository $tokenRepository;

    public function __construct(TokenRepository $tokenRepository)
    {
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get available tokens for club",
     *     summary="Get available tokens for club",
     *     tags={"Smart Contract", "Club"},
     *     @SWG\Response(response="200", description="success response"),
     * )
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     * @ListResponse(entityClass=SlimTokenResponse::class)
     * @Route("/club/{clubId}/tokens", methods={"GET"})
     */
    public function club(
        ClubRepository $clubRepository,
        ClubTokenRepository $clubTokenRepository,
        string $clubId
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_BAD_REQUEST);
        }

        $club = $clubRepository->findOneBy(['id' => $clubId]);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $participant = $club->getParticipant($currentUser);
        if (!in_array($participant->role, [ClubParticipant::ROLE_MODERATOR, ClubParticipant::ROLE_OWNER])) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $tokens = array_map(
            fn(ClubToken $token) => new SlimTokenResponse($token->token),
            $clubTokenRepository->findBy(['club' => $club])
        );

        return $this->handleResponse($tokens);
    }

    /**
     * @SWG\Post(
     *     description="Add new token",
     *     summary="Add new token",
     *     tags={"Smart Contract"},
     *     @SWG\Response(response="200", description="success response"),
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateTokenRequest::class)))
     * )
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     * @ViewResponse(entityClass=SlimTokenResponse::class)
     * @Route("", methods={"POST"})
     */
    public function add(Request $request, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var CreateTokenRequest $createTokenRequest */
        $createTokenRequest = $this->getEntityFromRequestTo($request, CreateTokenRequest::class);

        if ($this->tokenRepository->findOneBy(['tokenId' => $createTokenRequest->tokenId])) {
            return $this->createErrorResponse('token_id_already_exists', Response::HTTP_BAD_REQUEST);
        }

        $infuraURL = 'https://'.$_ENV['ETHEREUM_NETWORK_NAME'].'.infura.io/v3/'.$_ENV['ETHEREUM_INFURA_KEY'];

        $contractMeta = json_decode(file_get_contents(__DIR__.'/../../../../var/'.$_ENV['ETHEREUM_CONTACT_FILE_NAME']));
        $contract = new SmartContract(
            $contractMeta->abi,
            $contractMeta->networks->{$_ENV['ETHEREUM_NETWORK_ID']}->address,
            new Ethereum($infuraURL)
        );

        try {
            $url = $contract->uri(new EthD($createTokenRequest->tokenId))->val(); //@phpstan-ignore-line
        } catch (Throwable $exception) {
            $logger->error($exception);

            return $this->createErrorResponse('cannot_fetch_uri_by_token_id', Response::HTTP_BAD_REQUEST);
        }

        try {
            $metaData = json_decode(file_get_contents($url), true);
        } catch (Throwable $exception) {
            $logger->error($exception);

            return $this->createErrorResponse('cannot_fetch_metadata_from_uri', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $token = new Token();
        $token->tokenId = $createTokenRequest->tokenId;
        $token->description = $metaData['description'] ?? null;
        $token->name = $metaData['name'] ?? null;
        $token->initializedData = $metaData;
        $token->initializedAt = time();
        $token->network = $_ENV['ETHEREUM_NETWORK_ID'];
        $token->contractAddress = $createTokenRequest->addressId;
        $token->contractType = $createTokenRequest->contractType;
        $token->isInternal = $createTokenRequest->isInternal;
        $token->landingUrl = $createTokenRequest->landingUrl;
        $token->minAmount = $createTokenRequest->minAmount;

        $this->tokenRepository->save($token);

        return $this->handleResponse(new SlimTokenResponse($token));
    }

    /**
     * @SWG\Get(
     *     description="Get information about token by id",
     *     summary="Get information about token by id",
     *     tags={"Smart Contract"},
     *     @SWG\Response(response="200", description="success response"),
     *     @SWG\Parameter(in="query", required=false, name="walletAddress", type="string")
     * )
     * @ViewResponse(entityClass=TokenResponse::class)
     * @Route("/{tokenId}/info", methods={"GET"})
     */
    public function info(
        Request $request,
        string $tokenId,
        ClubTokenRepository $clubTokenRepository
    ): JsonResponse {
        $token = $this->tokenRepository->findOneBy(['tokenId' => $tokenId, 'isInternal' => true]);
        if (!$token) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $infuraURL = 'https://'.$_ENV['ETHEREUM_NETWORK_NAME'].'.infura.io/v3/'.$_ENV['ETHEREUM_INFURA_KEY'];

        $contractMeta = json_decode(file_get_contents(__DIR__.'/../../../../var/'.$_ENV['ETHEREUM_CONTACT_FILE_NAME']));
        $contract = new SmartContract(
            $contractMeta->abi,
            $contractMeta->networks->{$_ENV['ETHEREUM_NETWORK_ID']}->address,
            new Ethereum($infuraURL)
        );

        $url = $contract->uri(new EthQ($token->tokenId))->val(); //@phpstan-ignore-line

        if ($token->initializedAt === null) {
            try {
                $metaData = json_decode(file_get_contents($url), true);
                $token->name = $metaData['name'] ?? null;
                $token->description = $metaData['description'] ?? null;
                $token->initializedData = $metaData;
            } catch (Throwable $exception) {
            }
            $token->initializedAt = time();

            $this->tokenRepository->save($token);
        }

        $totalSupply = $contract->totalSupply(new EthQ($token->tokenId))->val(); //@phpstan-ignore-line
        $maxTokenSupply = $contract->maxTokenSupply(new EthQ($token->tokenId))->val(); //@phpstan-ignore-line
        $tokenPrice = (string) $contract->tokenPrice(new EthQ($token->tokenId))->val(); //@phpstan-ignore-line

        $balanceOf = null;
        if ($walletAddress = $request->query->get('walletAddress')) {
            try {
                if ($token->contractType == 'erc-721') {
                    $balanceOf = $contract->balanceOf(//@phpstan-ignore-line
                        new EthD($walletAddress),
                    )->val();
                } else {
                    $balanceOf = $contract->balanceOf(//@phpstan-ignore-line
                        new EthD($walletAddress),
                        new EthQ($token->tokenId),
                    )->val();
                }
            } catch (Throwable $exception) {
            }
        }

        $clubsTokens = $clubTokenRepository->findClubTokensForTokenId($tokenId);
        $clubs = array_map(fn(ClubToken $t) => new ClubResponse($t->club), $clubsTokens);

        return $this->handleResponse(new TokenResponse(
            $url,
            $token->contractAddress,
            $totalSupply,
            $maxTokenSupply,
            $tokenPrice,
            $infuraURL,
            $balanceOf,
            $clubs,
            $token->network,
        ));
    }

    /**
     * @SWG\Get(
     *     description="Get information about user tokens",
     *     summary="Get information about user tokens",
     *     tags={"Smart Contract"},
     *     @SWG\Response(response="200", description="success response")
     * )
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=UserTokenResponse::class)
     * @Route("/token", methods={"GET"})
     */
    public function getUserTokens(
        Request $request,
        UserTokenRepository $userTokenRepository
    ) {
        $currentUser = $this->getUser();

        $lastValue = $request->query->getInt('lastValue');
        $limit = $request->query->getInt('limit', 20);

        [$userTokens, $lastValue, $count] = $userTokenRepository->findByUser($currentUser, $lastValue, $limit);

        $userTokensResponse = array_map(
            fn(UserToken $userToken) => new UserTokenResponse(
                $userToken->tokenId,
                $userToken->name,
                $userToken->description,
                $userToken->nftImage->getResizerUrl()
            ),
            $userTokens
        );

        return $this->handleResponse(new PaginatedResponseWithCount($userTokensResponse, $lastValue, $count));
    }
}
