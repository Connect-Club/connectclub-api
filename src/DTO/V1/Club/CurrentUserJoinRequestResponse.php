<?php

namespace App\DTO\V1\Club;

use App\Entity\Club\JoinRequest;
use Swagger\Annotations as SWG;

class CurrentUserJoinRequestResponse
{
    /**
     * @SWG\Property(
     *     type="string",
     *     description="Join request status for current user",
     *     enum={
     *         JoinRequest::STATUS_MODERATION,
     *         JoinRequest::STATUS_CANCELLED,
     *         JoinRequest::STATUS_APPROVED,
     *     }
     * )
     */
    public string $joinRequestStatus;

    public string $joinRequestId;

    public string $clubId;

    public function __construct(JoinRequest $joinRequest)
    {
        $this->joinRequestStatus = $joinRequest->status;
        $this->joinRequestId = $joinRequest->id->toString();
        $this->clubId = $joinRequest->club->id->toString();
    }
}
