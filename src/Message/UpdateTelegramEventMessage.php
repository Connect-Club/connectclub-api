<?php

namespace App\Message;

final class UpdateTelegramEventMessage
{
    private array $update;

    public function __construct(array $update)
    {
        $this->update = $update;
    }

    public function getUpdate(): array
    {
        return $this->update;
    }
}
