<?php

namespace App\DTO\V2\VideoRoom;

use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V2\User\LanguageDTO;
use App\Entity\VideoChat\VideoRoom;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomTokenResponse extends \App\DTO\V1\VideoRoom\VideoRoomTokenResponse
{
    /**
     * @var string
     */
    public string $jitsiServer = '';

    /**
     * @var bool
     */
    public bool $isAdmin;

    /**
     * @var bool
     */
    public bool $isDone;

    /**
     * @var bool
     */
    public bool $isPrivate;

    /**
     * @var bool
     */
    public bool $isSpecialSpeaker;

    /**
     * @var string
     */
    public string $draftType;

    /**
     * @var ClubSlimResponse|null
     */
    public ?ClubSlimResponse $club = null;

    /**
     * @var string|null
     */
    public ?string $eventScheduleId = null;

    /**
     * @var LanguageDTO|null
     */
    public ?LanguageDTO $language;

    /**
     * @var string|null
     */
    public ?string $description;

    public function __construct(
        string $token,
        VideoRoom $videoRoom,
        string $name,
        ?string $description,
        int $id,
        string $sid,
        int $ownerId,
        bool $open,
        bool $isAdmin,
        bool $isSpecialSpeaker
    ) {
        $this->jitsiServer = $_ENV['JITSI_SERVER'];
        $this->isAdmin = $isAdmin;
        $this->isPrivate = $videoRoom->isPrivate;
        $this->isDone = $videoRoom->doneAt !== null;
        $this->draftType = $videoRoom->draftType ?? '';
        if ($videoRoom->eventSchedule) {
            $this->club = $videoRoom->eventSchedule->club ?
                          new ClubSlimResponse($videoRoom->eventSchedule->club) :
                          null;
            $this->eventScheduleId = $videoRoom->eventSchedule->id->toString();
        }
        $this->description = $videoRoom->community->description;
        $this->language = $videoRoom->language ? new LanguageDTO($videoRoom->language) : null;
        $this->isSpecialSpeaker = $isSpecialSpeaker;

        parent::__construct($token, $videoRoom, $name, $description, $id, $sid, $ownerId, $open);
    }
}
