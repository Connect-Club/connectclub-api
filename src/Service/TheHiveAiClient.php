<?php

namespace App\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

class TheHiveAiClient
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function checkPhotoSrc(string $photoSrc): array
    {
        $response = $this->client->request('POST', 'https://api.thehive.ai/api/v2/task/sync', [
            RequestOptions::FORM_PARAMS => [
                'url' => $photoSrc
            ],
            RequestOptions::HEADERS => [
                'Authorization' => 'token '.$_ENV['THE_HIVE_AI_API_TOKEN']
            ]
        ])->getBody()->getContents();

        return json_decode($response, true);
    }
}
