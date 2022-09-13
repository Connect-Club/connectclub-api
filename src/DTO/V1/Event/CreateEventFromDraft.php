<?php

namespace App\DTO\V1\Event;

class CreateEventFromDraft
{
    /** @var string|null */
    public ?string $eventScheduleId = null;

    /** @var string|null */
    public ?string $title = null;

    /** @var int|null */
    public ?int $language = null;

    /** @var bool|null */
    public ?bool $isPrivate = null;

    /** @var string|null */
    public ?string $userId = null;
}
