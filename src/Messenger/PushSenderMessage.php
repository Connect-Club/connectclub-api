<?php

namespace App\Messenger;

class PushSenderMessage
{
    /** @var array */
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
