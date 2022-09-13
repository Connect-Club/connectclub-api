<?php

namespace App\DTO\V1\Event;

class CreateCallRequest
{
    public string $userId;
    public int $language;
    public ?string $title = null;
}
