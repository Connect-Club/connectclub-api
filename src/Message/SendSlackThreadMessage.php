<?php

namespace App\Message;

class SendSlackThreadMessage
{
    /** @var string */
    public string $channel;

    /** @var string */
    public string $mainThreadMessage;

    /** @var string[] */
    public array $nestedThreadMessages;

    public function __construct(string $channel, string $mainThreadMessage, array $nestedThreadMessages)
    {
        $this->channel = $channel;
        $this->mainThreadMessage = $mainThreadMessage;
        $this->nestedThreadMessages = $nestedThreadMessages;
    }
}
