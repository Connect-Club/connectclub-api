<?php

namespace App\Service\Amplitude;

use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\AmplitudeGroupEventsStatisticsMessage;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class AmplitudeManager
{
    /** @var AmplitudeEventStatisticsMessage[] */
    private array $eventsBatch = [];

    private MessageBusInterface $bus;
    private ClientInterface $client;
    private LoggerInterface $logger;
    private string $legacyApiKey;
    private string $apiKey;

    public function __construct(
        MessageBusInterface $bus,
        ClientInterface $client,
        LoggerInterface $logger,
        string $legacyApiKey,
        string $apiKey
    ) {
        $this->bus = $bus;
        $this->client = $client;
        $this->logger = $logger;
        $this->legacyApiKey = $legacyApiKey;
        $this->apiKey = $apiKey;
    }

    public function sendEventForUser(
        AmplitudeUser $user,
        string $event,
        array $eventOptions = [],
        array $userOptions = []
    ) {
    }

    public function addEventToBatch(
        AmplitudeUser $user,
        string $event,
        array $eventOptions = []
    ): self {
        $this->eventsBatch[] = new AmplitudeEventStatisticsMessage(
            $event,
            $eventOptions,
            $user,
            $user->getDeviceId()
        );

        return $this;
    }

    public function flushBatch()
    {
        $this->bus->dispatch(new AmplitudeGroupEventsStatisticsMessage($this->eventsBatch));
        $this->eventsBatch = [];
    }

    public function sendEventsRequestToAmplitude(array $events)
    {
        $request = [
            'api_key' => $this->apiKey,
            'options' => [
                'min_id_length' => 1,
            ],
            'events' => $events,
        ];

        $requestBody = json_encode($request);
        try {
            $this->logger->debug('Request body for amplitude request: '.$requestBody);
            $response = $this->client->request('POST', 'https://api2.amplitude.com/2/httpapi', ['json' => $request]);
            $this->logger->debug($response->getBody()->getContents());
        } catch (ClientException $clientException) {
            /** @var ResponseInterface|null $response */
            $response = $clientException->getResponse();
            if (!$response) {
                return;
            }

            if ($response->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
                throw new TooManyRequestsHttpException();
            }

            $responseBody = $clientException->getResponse()->getBody()->getContents();

            $this->logger->error(sprintf(
                'Amplitude incorrect response %d: %s, request body: %s',
                $response->getStatusCode(),
                $responseBody,
                $requestBody
            ));
        } catch (ServerException $serverException) {
            $this->logger->error('Server exception connect to amplitude: '.$serverException->getMessage(), [
                'exception' => $serverException,
            ]);
        }
    }
}
