<?php

namespace App\Service;

use App\Entity\User\SmsVerification;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Throwable;

class IpQualityScoreClient
{
    private ClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function calculateFraudScore(SmsVerification $smsVerification): ?float
    {
        if (!$smsVerification->ip) {
            return null;
        }

        //phpcs:ignore
        $url = 'https://www.ipqualityscore.com/api/json/ip/w08BCwP5ZKpeX2ReJvl7ysAeGvG4wz01/'.$smsVerification->ip.'?strictness=true&allow_public_access_points=true&lighter_penalties=true';

        try {
            $response = $this->client->request('GET', $url, [
                RequestOptions::TIMEOUT => 1,
                RequestOptions::CONNECT_TIMEOUT => 1,
                RequestOptions::READ_TIMEOUT => 1
            ])->getBody();
        } catch (Throwable $exception) {
            $this->logger->error($exception, ['exception' => $exception]);
            return null;
        }

        $data = json_decode($response, true);

        return $data['fraud_score'] ?? null;
    }
}
