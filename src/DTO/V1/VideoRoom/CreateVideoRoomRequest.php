<?php

namespace App\DTO\V1\VideoRoom;

use App\Controller\ErrorCode;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class CreateVideoRoomRequest.
 */
class CreateVideoRoomRequest
{
    /**
     * @Assert\Length(
     *     min=3,
     *     max=40,
     *     maxMessage=ErrorCode::V1_VIDEO_ROOM_VALIDATION_DESCRIPTION_MAX_LENGTH,
     *     minMessage=ErrorCode::V1_VIDEO_ROOM_VALIDATION_DESCRIPTION_MIN_LENGTH
     * )
     * @Assert\NotBlank(message=ErrorCode::V1_VIDEO_ROOM_VALIDATION_DESCRIPTION_EMPTY)
     *
     * @var string|null
     */
    public ?string $description = null;

    /**
     * @var string|null
     */
    public ?string $name = null;
}
