<?php

namespace App\Client;

use App\Entity\User;
use App\Exception\IntercomContactAlreadyExistsException;
use App\Service\EventLogManager;
use App\Service\PhoneNumberManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IntercomClient
{
    private ClientInterface $client;
    private EventLogManager $eventLogManager;
    private PhoneNumberManager $phoneNumberManager;

    public function __construct(
        ClientInterface $client,
        PhoneNumberManager $phoneNumberManager,
        EventLogManager $eventLogManager
    ) {
        $this->client = $client;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->eventLogManager = $eventLogManager;
    }

    public function updateContact(User $user, array $customAttributes)
    {
        try {
            $intercomId = $user->intercomId;
            $this->request('PUT', 'contacts/'.$intercomId, $this->getContactBodyForUser($user, $customAttributes));
        } catch (RequestException $requestException) {
            if ($requestException->getResponse()->getStatusCode() != Response::HTTP_CONFLICT) {
                throw $requestException;
            }
        }
    }

    public function registerContact(User $user, array $customAttributes): array
    {
        try {
            return $this->request('POST', 'contacts', $this->getContactBodyForUser($user, $customAttributes));
        } catch (RequestException $requestException) {
            if ($requestException->getResponse()->getStatusCode() == Response::HTTP_CONFLICT) {
                throw new IntercomContactAlreadyExistsException($user->username);
            }

            throw $requestException;
        }
    }

    public function findIntercomContact(User $user): ?array
    {
        $items = $this->request('POST', 'contacts/search', [
            'query' => [
                'field' => 'external_id',
                'operator' => '=',
                'value' => $user->username ?? (string) $user->id,
            ]
        ]);

        return $items['data'][0] ?? null;
    }

    public function request(string $method, string $uri, array $body = []): array
    {
        if ($_ENV['STAGE'] == 1) {
            return [];
        }

        $options = [
            RequestOptions::READ_TIMEOUT => 2,
            RequestOptions::TIMEOUT => 2,
            RequestOptions::CONNECT_TIMEOUT => 2,

            RequestOptions::JSON => $body,

            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$_ENV['INTERCOM_TOKEN'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        $start = microtime(true);
        try {
            $res = $this->client->request($method, 'https://api.intercom.io/'.$uri, $options);
            $end = microtime(true);

            $responseBody = $res->getBody()->getContents();
            $responseArray = json_decode($responseBody, true);

            $this->eventLogManager->logEventCustomObject('intercom_request_success', $uri, $method, [
                'request' => $options,
                'response' => $responseBody,
                'time' => $end - $start,
            ]);

            return $responseArray;
        } catch (RequestException $serverException) {
            $res = $serverException->getResponse();
            $responseBody = $res->getBody()->getContents();
            $end = microtime(true);

            $this->eventLogManager->logEventCustomObject('intercom_request_fail', $uri, $method, [
                'request' => $options,
                'response' => $responseBody,
                'time' => $end - $start,
                'error' => $serverException->getMessage(),
            ]);

            throw $serverException;
        }
    }

    public function getContactHash(User $user, array $customAttributes): string
    {
        $body = $this->getContactBodyForUser($user, $customAttributes);

        ksort($body);

        return md5(json_encode($body));
    }

    private function getContactBodyForUser(User $user, array $customAttributes): array
    {

        $body = [
            'role' => 'user',
            'external_id' => $user->username ?? $user->id,
            'phone' => $user->phone ? $this->phoneNumberManager->formatE164($user->phone) : null,
            'name' => $user->getFullNameOrUsername(),
            'avatar' => $user->getAvatarSrc(250, 250),
            'last_seen_at' => $user->lastTimeActivity,
            'signed_up_at' => $user->createdAt,
        ];

        if ($customAttributes) {
            $body['custom_attributes'] = $customAttributes;
        }

        return $body;
    }
}
