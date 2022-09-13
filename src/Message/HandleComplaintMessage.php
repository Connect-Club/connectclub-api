<?php

namespace App\Message;

class HandleComplaintMessage
{
    public string $userId;
    public string $conferenceId;
    public string $message;
    public string $threadTsSlack;
    public ?string $delayedRequestId = null;
    public ?string $language = null;
    public int $attempt = 1;

    public function __construct(
        string $userId,
        string $conferenceId,
        string $message,
        string $threadTsSlack,
        ?string $delayedRequestId = null
    ) {
        $this->userId = $userId;
        $this->conferenceId = $conferenceId;
        $this->message = $message;
        $this->threadTsSlack = $threadTsSlack;
        $this->delayedRequestId = $delayedRequestId;
    }
}
