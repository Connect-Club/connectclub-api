<?php

namespace App\Message;

class SendNotificationMessageBatch
{
    /** @var SendNotificationMessage[] */
    private array $batch;

    public function __construct(array $batch)
    {
        $this->batch = $batch;
    }

    public function getBatch(): array
    {
        return $this->batch;
    }

    public function idempotentKey(): string
    {
        return sha1(
            implode(
                '-',
                array_map(fn(SendNotificationMessage $m) => $m->idempotentKey(), $this->batch)
            )
        );
    }
}
