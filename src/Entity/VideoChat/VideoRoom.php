<?php

namespace App\Entity\VideoChat;

use App\Annotation\SerializationContext;
use App\ConnectClub;
use App\Entity\Chat\GroupChat;
use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\Interest\Interest;
use App\Entity\Log\LoggableEntityInterface;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoRoom\Archetype;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\VideoRoomRepository")
 */
class VideoRoom implements LoggableEntityInterface
{
    const TYPE_NATIVE = 'native';
    const TYPE_UNITY = 'unity';
    const TYPE_NEW = 'new';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     *
     * @Groups({"v1.room.default"})
     */
    public ?int $id;

    /** @ORM\Column(type="string", options={"default": VideoRoom::TYPE_NATIVE}) */
    public string $type = self::TYPE_NATIVE;

    /**
     * @Groups({"default"})
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    public bool $open = false;

    /**
     * @var Community
     * @ORM\OneToOne(targetEntity="App\Entity\Community\Community", mappedBy="videoRoom", cascade={"persist"})
     */
    public Community $community;

    /**
     * @var int
     * @ORM\Column(type="bigint")
     *
     * @Groups({"v1.room.default"})
     */
    public int $createdAt;

    /**
     * @var VideoRoomConfig
     * @ORM\OneToOne(
     *     targetEntity="App\Entity\VideoChat\VideoRoomConfig",
     *     cascade={"all"},
     *     inversedBy="videoRoom",
     *     fetch="EAGER"
     * )
     * @Groups({"v1.room.default"})
     */
    public VideoRoomConfig $config;

    /**
     * @var ArrayCollection|VideoMeeting[]
     * @ORM\OneToMany(targetEntity="App\Entity\VideoChat\VideoMeeting", mappedBy="videoRoom", cascade={"all"})
     */
    public Collection $meetings;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\VideoChat\VideoRoomBan", mappedBy="videoRoom", cascade={"all"})
     */
    public Collection $bans;

    /**
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\VideoChat\VideoRoomObject",
     *     mappedBy="videoRoom",
     *     cascade={"all"},
     *     orphanRemoval=true
     * )
     */
    public Collection $objects;

    /**
     * @ORM\JoinColumn(nullable=true)
     * @ORM\OneToOne(targetEntity="App\Entity\VideoChat\VideoRoom")
     */
    public ?VideoRoom $recoveryRoom = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User\Language") */
    public ?User\Language $language = null;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    public bool $matchingEnabled = false;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    public bool $notificationStartRoomHandled = false;

    /** @ORM\Column(type="datetime", nullable=true) */
    public ?\DateTime $deletedAt = null;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $maxParticipants = null;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Event\EventSchedule", inversedBy="videoRoom", cascade="persist")
     */
    public ?EventSchedule $eventSchedule = null;


    /**
     * @var Collection|User[]
     * @ORM\ManyToMany(targetEntity="App\Entity\User", cascade="all")
     */
    public Collection $invitedUsers;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public ?int $startedAt = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public ?int $doneAt = null;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    public bool $isPrivate = false;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public ?User $forPersonallyOnBoarding = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $draftType = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Subscription\Subscription", inversedBy="videoRooms") */
    public ?Subscription $subscription = null;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $isReception = false;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $alwaysReopen = false;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $alwaysOnline = false;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $ignoredVideoRoomObjectsIds = [];

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $guests = [];

    /**
     * Room constructor.
     */
    public function __construct(Community $community)
    {
        $this->invitedUsers = new ArrayCollection();
        $this->meetings = new ArrayCollection();
        $this->bans = new ArrayCollection();
        $this->objects = new ArrayCollection();
        $this->config = new VideoRoomConfig(2, 2, 1, 1, 5, 480, 3000, 300, new VideoRoomQuality(480, 360));
        $this->createdAt = time();
        $this->community = $community;
    }

    public function getObjects() : Collection
    {
        $objects = $this->objects;

        if ($this->config->backgroundRoom) {
            foreach ($this->config->backgroundRoom->objects as $backgroundObject) {
                $ignoredVideoRoomObjectsIds = $this->ignoredVideoRoomObjectsIds ?? [];
                if (!$objects->contains($backgroundObject) &&
                    !in_array($backgroundObject->id, $ignoredVideoRoomObjectsIds)) {
                    $objects->add($backgroundObject);
                }
            }
        }

        return $objects;
    }

    public function addObject(VideoRoomObject $object): self
    {
        if (!$this->getObjects()->contains($object)) {
            $this->objects->add($object);
        }

        return $this;
    }

    public function ignoreBackgroundObject(VideoRoomObject $object): self
    {
        $this->ignoredVideoRoomObjectsIds[] = $object->id;
        $this->ignoredVideoRoomObjectsIds = array_unique($this->ignoredVideoRoomObjectsIds);

        return $this;
    }

    public function getActiveMeeting(): ?VideoMeeting
    {
        $meeting = $this->meetings->matching(Criteria::create()->where(
            Criteria::expr()->isNull('endTime')
        ))->first();

        return $meeting ? $meeting : null;
    }

    public function addInvitedUser(User $user): self
    {
        if (!$this->invitedUsers->contains($user)) {
            $this->invitedUsers->add($user);
        }

        return $this;
    }

    public function getEntityCode(): string
    {
        return 'video_room';
    }

    public function mustPayForAccess(User $user): bool
    {
        if (!$this->subscription || !$this->subscription->isActive) {
            return false;
        }

        $community = $this->community;

        return !$community->owner->equals($user)
            && !$community->isModerator($user)
            && !$community->isAdmin($user);
    }

    public function isInvitedUser(User $userToCheck): bool
    {
        return $this->invitedUsers->exists(fn(int $key, User $user) => $userToCheck->equals($user));
    }
}
