<?php

namespace App\Service;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Entity\User;
use App\Exception\ApiException;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class MatchingClient
{
    private Producer $producer;
    private ClientInterface $client;
    private EventLogManager $eventLogManager;
    private LoggerInterface $logger;

    public function __construct(
        Producer $producer,
        ClientInterface $client,
        EventLogManager $eventLogManager,
        LoggerInterface $logger
    ) {
        $this->producer = $producer;
        $this->logger = $logger;
        $this->client = $client;
        $this->eventLogManager = $eventLogManager;
    }

    public function publishEvent(string $eventId, User $user, array $additionalData = [])
    {
        $this->request($eventId, array_merge(
            [
                'username' => $user->username,
                'ownerId' => $user->id,
                'firstName' => $user->name,
                'lastName' => $user->surname,
                'languages' => $user->languages,
                'isTester' => $user->isTester,
                'state' => $user->state,
            ],
            $additionalData
        ));
    }

    public function publishEventOwnedById(string $eventId, int $userId, array $additionalData = [])
    {
        $this->request($eventId, array_merge(['ownerId' => $userId], $additionalData));
    }

    public function publishEventOwnedBy(string $eventId, User $user, array $additionalData = [])
    {
        $this->request($eventId, array_merge(['ownerId' => $user->id], $additionalData));
    }

    public function findPeopleMatchingForUser(User $user, int $limit, ?string $lastValue = null): array
    {
        return $this->httpRequest('GET', '/users/getUserFollowRecommendations', [
            RequestOptions::QUERY => [
                'userId' => $user->id,
                'limit' => $limit,
                'lastValue' => $lastValue,
            ]
        ]);
    }

    public function findClubMatchingForUser(User $user, int $limit, ?string $lastValue = null): array
    {
        return $this->httpRequest('GET', '/users/getUserClubsRecommendations', [
            RequestOptions::QUERY => [
                'userId' => $user->id,
                'limit' => $limit,
                'lastValue' => $lastValue,
            ]
        ]);
    }

    public function findEventScheduleForUser(User $user, bool $isCalendar, int $limit, ?string $lastValue = null): array
    {
        $endpoint = $isCalendar ? 'getCalendarEventsRecommendations' : 'getUpcomingEventsRecommendations';

        return $this->httpRequest('GET', '/users/'.$endpoint, [
            RequestOptions::QUERY => [
                'userId' => $user->id,
                'limit' => $limit,
                'lastValue' => $lastValue,
            ]
        ]);
    }

    /**
     * @return array{
     *           'data': array{
     *             array{
     *               name: string,
     *               tokenId: string,
     *               image: string,
     *               description: string|null
     *             },
     *           },
     *           'code' : int
     *         }
     */
    public function findTokensForUser(User $user): array
    {
        return $this->httpRequest('GET', '/users/getUserTokens', [
            RequestOptions::QUERY => [
                'userId' => $user->id,
            ]
        ]);
    }

    /**
     * @return array{
     *     data: array{
     *       items: array{
     *         array{
     *            userId: int,
     *            name: string,
     *            tokenId: string,
     *            image: string,
     *            description: string,
     *         }
     *       },
     *       lastValue: int|null,
     *       totalValue: int
     *     },
     * }
     */
    public function findTokens(int $limit, int $lastValue): array
    {
        return $this->httpRequest('GET', '/users/getTokens', [
            RequestOptions::QUERY => [
                'lastValue' => $lastValue,
                'limit' => $limit
            ]
        ]);
    }

    private function httpRequest(string $method, string $endpoint, array $options): array
    {
        $url = $_ENV['PEOPLE_MATCHING_URL'].$endpoint;

        try {
            $res = $this->client->request($method, $url, array_merge([
                RequestOptions::CONNECT_TIMEOUT => 3,
                RequestOptions::TIMEOUT => 3,
                RequestOptions::READ_TIMEOUT => 3,
            ], $options));
        } catch (RequestException $exception) {
            $res = $exception->getResponse();
        }

        $responseBody = '';
        if ($res) {
            $responseBody = $res->getBody()->getContents();
        }

        $this->eventLogManager->logEventCustomObject(
            'people_matching_service_call',
            'call',
            $url,
            ['request' => $options, 'response' => $responseBody]
        );

        $responseArray = json_decode($responseBody, true);
        if (!$responseArray) {
            throw new RuntimeException('Matching service return unexpected response: '.json_last_error_msg());
        }

        if (isset($responseArray['err'])) {
            throw new ApiException(
                $responseArray['err'],
                $responseArray['code'] ?? Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $responseArray;
    }

    private function request(string $eventId, array $additionalData = [])
    {
        $body = json_encode(['id' => $eventId, 'data' => $additionalData]);

        try {
            $this->producer->publishToExchange('matching', $body);
        } catch (Exception $exception) {
            $this->logger->error($exception, ['exception' => $exception]);
        }
    }
}
