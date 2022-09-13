<?php

namespace App\Service;

use App\Message\SendSlackMessage;
use App\Message\SendSlackThreadMessage;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class SlackClient
{
    private ClientInterface $client;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;

    public function __construct(ClientInterface $client, MessageBusInterface $bus, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->bus = $bus;
        $this->logger = $logger;
    }

    public function sendMessageWithThread(string $channel, string $textMainThreadMessage, ...$textsNestedMessages)
    {
        $this->bus->dispatch(new SendSlackThreadMessage($channel, $textMainThreadMessage, $textsNestedMessages));
    }

    public function sendMessage(string $channel, string $text, string $replyToThread = null, bool $async = true): array
    {
        $request = [
            'text' => $text,
            'channel' => $channel,
        ];

        if ($replyToThread) {
            $request['thread_ts'] = $replyToThread;
        }

        if ($async) {
            return $this->sendSlackRequestAsync('POST', 'chat.postMessage', [RequestOptions::JSON => $request]);
        } else {
            return $this->sendSlackRequestSync('POST', 'chat.postMessage', ['json' => $request]);
        }
    }

    public function sendSlackRequestAsync(string $method, string $apiMethod, array $options = []) : array
    {
        $this->bus->dispatch(new SendSlackMessage($method, $apiMethod, $options));

        return [];
    }

    public function sendSlackRequestSync(string $method, string $apiMethod, array $options = []) : ?array
    {
        $options['headers'] ??= [];
        $options['headers']['Authorization'] = 'Bearer '.$_ENV['SLACK_BOT_ACCESS_TOKEN'];
        $options['headers']['Content-Type'] = 'application/json; charset=UTF-8;';

        try {
            $jsonResponse = $this->client
                ->request($method, 'https://slack.com/api/'.$apiMethod, $options)
                ->getBody()
                ->getContents();

            $responseArray = json_decode($jsonResponse, true);
            if (!$responseArray) {
                throw new \Exception('Incorrect json response from slack: '.$jsonResponse);
            }

            if (!$responseArray['ok']) {
                throw new \Exception('Bad request '.$jsonResponse);
            }

            return $responseArray;
        } catch (ClientException $clientException) {
            if ($clientException->getResponse()->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
                throw $clientException;
            }

            $this->logger->error(sprintf(
                'Slack %s error: %s, response: %s',
                $clientException->getRequest()->getUri(),
                $clientException->getMessage(),
                $clientException->getResponse()->getBody()->getContents()
            ));
        } catch (ServerException $serverException) {
            $this->logger->error(sprintf(
                'Slack %s error: %s, response: %s',
                $serverException->getRequest()->getUri(),
                $serverException->getMessage(),
                $serverException->getResponse()->getBody()->getContents()
            ));
        } catch (\Exception $exception) {
            $this->logger->error('Slack '.$apiMethod.' error: '.$exception->getMessage());
        }

        return [];
    }
}
