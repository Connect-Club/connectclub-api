<?php

namespace App\Messenger;

use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class PushSenderSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = json_decode($encodedEnvelope['body'], true) ?? [];

        return new Envelope(new PushSenderMessage($body));
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        if (!$message instanceof PushSenderMessage) {
            throw new Exception('Transport & serializer support only PushSenderMessage got '.get_class($message));
        }

        return $message->getData();
    }
}
