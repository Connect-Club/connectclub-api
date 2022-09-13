<?php

namespace App\MessageHandler;

use App\Message\SendSlackThreadMessage;
use App\Service\SlackClient;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SendSlackThreadMessageHandler implements MessageHandlerInterface
{
    private SlackClient $slackClient;

    public function __construct(SlackClient $slackClient)
    {
        $this->slackClient = $slackClient;
    }

    public function __invoke(SendSlackThreadMessage $message)
    {
        $threadId = $this->slackClient->sendMessage($message->channel, $message->mainThreadMessage, null, false)['ts'];

        foreach ($message->nestedThreadMessages as $nestedThreadMessage) {
            $this->slackClient->sendMessage($message->channel, $nestedThreadMessage, $threadId, true);
        }
    }
}
