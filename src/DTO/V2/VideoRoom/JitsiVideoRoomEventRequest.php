<?php

namespace App\DTO\V2\VideoRoom;

use Swagger\Annotations as SWG;

class JitsiVideoRoomEventRequest
{
    /** @var string */
    public string $eventType;

    /** @var string */
    public string $conferenceGid;

    /** @var string */
    public string $conferenceId;

    /** @var string|null */
    public ?string $endpointId = null;

    /** @var string|null */
    public ?string $endpointUuid = null;

    /** @var bool|null */
    public ?bool $endpointAllowIncomingMedia = null;

    /**
     * @SWG\Property(@SWG\Items(type="string"))
     * @var string[]|null
     */
    public ?array $payload = null;

    /** @var int|null */
    public ?int $createdAt = null;
}
