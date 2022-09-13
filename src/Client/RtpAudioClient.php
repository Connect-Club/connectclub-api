<?php

namespace App\Client;

use App\Service\EventLogManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use Throwable;

class RtpAudioClient
{
    private ClientInterface $client;
    private EventLogManager $eventLogManager;

    public function __construct(ClientInterface $client, EventLogManager $eventLogManager)
    {
        $this->client = $client;
        $this->eventLogManager = $eventLogManager;
    }

    public function startSpeechRecognition(string $conferenceId, string $userId, string $languageCode): array
    {
        $query = [
            'pipelineId' => $conferenceId,
            'endpoint' => $userId,
            'languageCode' => $languageCode
        ];

        $responseData = $this->request('POST', '/speech-to-text', [RequestOptions::QUERY => $query]);

        if (!isset($responseData['RequestId'])) {
            throw new RuntimeException('Unexpected response from RTP audio service');
        }

        return $responseData;
    }

    public function checkSpeechRecognition(string $requestId): array
    {
        return $this->request('GET', '/speech-to-text', [
            RequestOptions::QUERY => [
                'requestId' => $requestId
            ]
        ]);
    }

    private function request(string $method, string $url, array $options = []): array
    {
        $responseData = [];

        try {
            $response = $this->client->request($method, $_ENV['RTP_AUDIO_HOST'].$url, array_merge([
                RequestOptions::TIMEOUT => 2,
                RequestOptions::READ_TIMEOUT => 2,
                RequestOptions::CONNECT_TIMEOUT => 2
            ], $options));

            $responseData = $logContext = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $requestException) {
            if ($errorResponse = $requestException->getResponse()) {
                $logContext = [
                    'response' => json_decode($errorResponse->getBody()->getContents(), true),
                    'error' => $requestException->getMessage(),
                ];
            } else {
                $logContext = ['error' => $requestException->getMessage()];
            }
        } catch (Throwable $exception) {
            $logContext = ['error' => $exception->getMessage()];
        }

        $this->eventLogManager->logEventCustomObject('rtp_audio_request', 'user_id', $url, [
            'method' => $method,
            'request' => $options,
            'log' => $logContext,
        ]);

        return $responseData;
    }
}
