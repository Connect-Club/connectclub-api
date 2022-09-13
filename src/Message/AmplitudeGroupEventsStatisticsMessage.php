<?php

namespace App\Message;

final class AmplitudeGroupEventsStatisticsMessage
{
    /** @var AmplitudeEventStatisticsMessage[] */
    private array $batch;

    public function __construct(array $batch)
    {
        $this->batch = $batch;
    }

    public function getBatch(): array
    {
        return $this->batch;
    }
}
