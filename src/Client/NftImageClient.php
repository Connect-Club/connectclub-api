<?php

namespace App\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;

class NftImageClient
{
    private const DEFAULT_TIMEOUT = 60;

    private ClientInterface $httpClient;

    public function __construct(
        ClientInterface $httpClient
    ) {
        $this->httpClient = $httpClient;
    }

    public function syncDownload(string $imageUrl): array
    {
        $imageUrl = $this->processImageUrl($imageUrl);

        $tmpFilePath = stream_get_meta_data(tmpfile())['uri'];
        $response = $this->httpClient->request(
            'GET',
            $imageUrl,
            [
                RequestOptions::SINK => $tmpFilePath,
                RequestOptions::TIMEOUT => self::DEFAULT_TIMEOUT,
                RequestOptions::CONNECT_TIMEOUT => self::DEFAULT_TIMEOUT,
                RequestOptions::READ_TIMEOUT => self::DEFAULT_TIMEOUT,
                'headers' => [
                    //phpcs:ignore
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36'
                ]
            ]
        );

        return [$response, $tmpFilePath];
    }

    /**
     * @param string $imageUrl
     * @return array{
     *     PromiseInterface,
     *     string
     * }
     */
    public function asyncDownload(string $imageUrl): array
    {
        $imageUrl = $this->processImageUrl($imageUrl);

        $tmpFilePath = stream_get_meta_data(tmpfile())['uri'];
        $promise = $this->httpClient->requestAsync(
            'GET',
            $imageUrl,
            [
                RequestOptions::SINK => $tmpFilePath,
                RequestOptions::TIMEOUT => self::DEFAULT_TIMEOUT,
                RequestOptions::CONNECT_TIMEOUT => self::DEFAULT_TIMEOUT,
                RequestOptions::READ_TIMEOUT => self::DEFAULT_TIMEOUT,
                'headers' => [
                    //phpcs:ignore
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36'
                ]
            ]
        );

        return [$promise, $tmpFilePath];
    }

    public static function getFileExtension(Response $response): ?string
    {
        $contentType = $response->getHeaderLine('Content-Type');
        if (false === strpos($contentType, 'image/')) {
            return null;
        }

        $contentType = explode('/', $contentType);

        return array_pop($contentType);
    }

    private function processImageUrl(string $imageUrl): string
    {
        return str_replace('ipfs://', 'https://ipfs.io', $imageUrl);
    }
}
