<?php

namespace App\DTO\V1\Club;

use App\Entity\Club\Club;
use App\Entity\Club\JoinRequest;
use App\Entity\Club\ClubParticipant;
use Swagger\Annotations as SWG;

class ClubFullResponse extends ClubResponse
{
    /**
     * @SWG\Property(
     *     type="string",
     *     description="Role for current user",
     *     enum={
     *         null,
     *         ClubParticipant::ROLE_MODERATOR,
     *         ClubParticipant::ROLE_OWNER,
     *         ClubParticipant::ROLE_MEMBER,
     *     }
     * )
     */
    public ?string $clubRole;

    /**
     * @SWG\Property(
     *     type="string",
     *     description="Role for current user",
     *     enum={
     *         null,
     *         ClubParticipant::ROLE_MODERATOR,
     *         ClubParticipant::ROLE_OWNER,
     *         ClubParticipant::ROLE_MEMBER,
     *     }
     * )
     */
    public ?string $role;

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
    public ?string $joinRequestStatus = null;

    /** @var string|null */
    public ?string $joinRequestId = null;

    /** @var ClubUser[] */
    public array $members = [];

    /** @var bool */
    public bool $isPublic;

    /** @var bool */
    public bool $togglePublicModeEnabled;

    public function __construct(
        Club $club,
        ?string $role,
        int $countParticipants = 0,
        ?JoinRequest $joinRequest = null,
        array $previewParticipants = []
    ) {
        parent::__construct($club, $countParticipants);

        $this->clubRole = $this->role = $role;

        if ($joinRequest) {
            $this->joinRequestStatus = $joinRequest->status;
            $this->joinRequestId = $joinRequest->id->toString();
        }

        foreach ($previewParticipants as $participant) {
            $this->members[] = new ClubUser($participant->user);
        }

        $this->isPublic = $club->isPublic;
        $this->togglePublicModeEnabled = $club->togglePublicModeEnabled;
    }
}
