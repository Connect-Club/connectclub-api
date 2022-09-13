<?php

namespace App\Entity\Event;

use App\Entity\Club\Club;
use App\Entity\Ethereum\Token;
use App\Entity\EventScheduleFestivalScene;
use App\Entity\Interest\Interest;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Event\EventScheduleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventScheduleRepository::class)
 */
class EventSchedule
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $owner;

    /** @ORM\Column(type="string") */
    public string $name;

    /**
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\Event\EventScheduleParticipant",
     *     mappedBy="event",
     *     cascade="all",
     *     orphanRemoval=true
     * )
     * @var Collection|EventScheduleParticipant[]
     */
    public Collection $participants;

    /** @ORM\Column(type="bigint") */
    public int $dateTime;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $description = null;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /**
     * @ORM\OneToOne(
     *     targetEntity="App\Entity\VideoChat\VideoRoom",
     *     mappedBy="eventSchedule",
     *     cascade="persist",
     *     fetch="EAGER"
     * )
     */
    public ?VideoRoom $videoRoom = null;

    /**
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\Event\EventScheduleInterest",
     *     orphanRemoval=true,
     *     cascade={"all"},
     *     mappedBy="eventSchedule"
     * )
     */
    public Collection $interests;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $languages = ['EN'];

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $festivalCode = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\EventScheduleFestivalScene") */
    public ?EventScheduleFestivalScene $festivalScene = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User\Language") */
    public ?User\Language $language = null;

    /** @ORM\ManyToOne(targetEntity=Subscription::class) */
    public ?Subscription $subscription = null;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $endDateTime = null;

    /** @ORM\Column(type="datetime", nullable=true) */
    public ?\DateTime $deletedAt = null;

    /** @ORM\ManyToOne(targetEntity=Club::class) */
    public ?Club $club = null;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $forMembersOnly = false;

    /**
     * @var Collection|ArrayCollection|EventToken[]
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\Event\EventToken",
     *     mappedBy="eventSchedule",
     *     cascade="all",
     *     orphanRemoval=true
     * )
     */
    public Collection $forOwnerTokens;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $isPrivate = false;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $isTokensRequired = false;

    public function __construct(
        User $owner,
        string $name,
        int $dateTime,
        ?string $description,
        User\Language $language = null
    ) {
        $this->id = Uuid::uuid4();
        $this->owner = $owner;
        $this->participants = new ArrayCollection();
        $this->interests = new ArrayCollection();
        $this->forOwnerTokens = new ArrayCollection();
        $this->name = $name;
        $this->dateTime = $dateTime;
        $this->description = $description;
        $this->language = $language;
        $this->languages = $language ? [$language->code] : $this->languages;
        $this->createdAt = time();
    }

    public function addInterest(Interest $interest): self
    {
        if ($this->interests->filter(fn(EventScheduleInterest $i) => $i->interest->id == $interest->id)->isEmpty()) {
            $this->interests->add(new EventScheduleInterest($this, $interest));
        }

        return $this;
    }

    public function clearInterests(): self
    {
        $this->interests->clear();

        return $this;
    }
}
