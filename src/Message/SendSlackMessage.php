<?php

namespace App\Message;

class SendSlackMessage
{
    public string $method;
    public string $apiMethod;
    public array $options = [];

    public function __construct(string $method, string $apiMethod, array $options)
    {
        $this->method = $method;
        $this->apiMethod = $apiMethod;
        $this->options = array_filter($options, fn($value) => is_scalar($value) || is_array($value));
    }
}
