<?php

namespace App\Tests\Mock;

use Mockery;
use App\Tests\Auth\FacebookAuthCest;
use App\Tests\Auth\GoogleAuthCest;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MockHttpClient implements ClientInterface
{
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return Mockery::mock(ResponseInterface::class);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return Mockery::mock(PromiseInterface::class);
    }

    public function request(string $method, $uri, array $options = []): ResponseInterface
    {
        if (in_array($uri, [GoogleAuthCest::GOOGLE_AVATAR_PICTURE_768, FacebookAuthCest::FACEBOOK_MAIN_PICTURE])) {
            if ($sink = $options[RequestOptions::SINK] ?? null) {
                file_put_contents($sink, file_get_contents(__DIR__.'/../../_data/video_room_background.png'));
            }
        }

        return Mockery::mock(ResponseInterface::class);
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        return Mockery::mock(PromiseInterface::class);
    }

    public function getConfig(?string $option = null)
    {
    }
}
