<?php

namespace App\DTO\V1\VideoRoom;

use App\Annotation\SerializationContext;
use App\Entity\Community\CommunityParticipant;
use App\Entity\VideoChat\VideoRoom;
use Doctrine\Common\Collections\Criteria;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomResponse
{
    /**
     * @var int|null
     * @Groups({"default"})
     */
    public ?int $id;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $about;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $name;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $createdAt;

    /**
     * @var VideoRoomConfigResponse
     * @Groups({"default"})
     */
    public VideoRoomConfigResponse $config;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $description = null;

    /**
     * @SWG\Property(type="object")
     * @Groups({"default"})
     * @SerializationContext(serializeAsObject=true)
     */
    public array $custom = [];

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $ownerId;

    /**
     * @var int[]
     * @Groups({"default"})
     */
    public array $adminsIds = [];

    /**
     * @var int[]
     * @Groups({"default"})
     */
    public array $specialGuests = [];

    /**
     * @var int[]
     * @Groups({"default"})
     */
    public array $speakerIds = [];

    /**
     * @var bool
     * @Groups({"default"})
     */
    public bool $isDone;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $draftType;

    public function __construct(VideoRoom $room)
    {
        $this->id = $room->id;
        $this->about = $room->community->about ?? '';
        $this->name = $room->community->name;
        $this->createdAt = $room->createdAt;
        $this->description = $room->community->description;
        $this->config = VideoRoomConfigResponse::createFromVideoRoomConfig($room);
        $this->ownerId = (int) $room->community->owner->id;
        $this->adminsIds = $room->community->participants->matching(Criteria::create()->where(
            Criteria::expr()->eq('role', CommunityParticipant::ROLE_ADMIN)
        ))->map(fn(CommunityParticipant $p) => $p->user->id)->getValues();
        $this->specialGuests = $room->community->participants->matching(Criteria::create()->where(
            Criteria::expr()->eq('role', CommunityParticipant::ROLE_SPECIAL_GUESTS)
        ))->map(fn(CommunityParticipant $p) => $p->user->id)->getValues();
        $this->specialGuests = array_values($this->specialGuests);
        $this->speakerIds = array_unique(array_merge($this->specialGuests, $this->adminsIds));
        $this->isDone = $room->doneAt !== null;
        $this->draftType = $room->draftType ?? '';
    }
}
