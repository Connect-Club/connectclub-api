<?php

namespace App\Service\Notification;

class Message
{
    private ?string $message;
    private array $messageParameters = [];

    public function __construct(?string $message, array $messageParameters)
    {
        $this->message = $message;
        $this->messageParameters = $messageParameters;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getMessageParameters(): array
    {
        return $this->messageParameters;
    }

    public function getMessageParameter(string $parameter, $default = null)
    {
        return $this->messageParameters[$parameter] ?? $default;
    }
}
