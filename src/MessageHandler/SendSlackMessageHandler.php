<?php

namespace App\MessageHandler;

use App\Message\SendSlackMessage;
use App\Service\SlackClient;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SendSlackMessageHandler implements MessageHandlerInterface
{
    private SlackClient $slackClient;

    public function __construct(SlackClient $slackClient)
    {
        $this->slackClient = $slackClient;
    }

    public function __invoke(SendSlackMessage $message)
    {
        $this->slackClient->sendSlackRequestSync($message->method, $message->apiMethod, $message->options);
    }
}
