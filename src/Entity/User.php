<?php

namespace App\Entity;

use App\ConnectClub;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Community\Community;
use App\Entity\Interest\Interest;
use App\Entity\Invite\Invite;
use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Entity\Log\LoggableEntityInterface;
use App\Entity\OAuth\AccessToken;
use App\Entity\Photo\UserPhoto;
use App\Entity\Subscription\Subscription;
use App\Entity\User\AppleProfileData;
use App\Entity\User\Device;
use App\Entity\User\FacebookProfileData;
use App\Entity\User\GoogleProfileData;
use App\Entity\User\Language;
use App\OAuth2\OAuth2UserState;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Entity\Subscription\PaidSubscription;

/**
 * @Gedmo\SoftDeleteable(fieldName="deletedAt")
 * @ORM\Table(
 *     name="users",
 *     indexes={
 *         @ORM\Index(name="user_phone_number", columns={"phone"}),
 *         @ORM\Index(name="user_delete_new_badge_at", columns={"delete_new_badge_at"}, options={
 *             "where": "(delete_new_badge_at IS NOT NULL)"
 *         })
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface, LoggableEntityInterface
{
    const STATE_OLD_USER = 'old';
    const STATE_DELETED = 'deleted';
    const STATE_BANNED = 'banned';
    const STATE_NOT_INVITED = 'not_invited';
    const STATE_INVITED = 'invited';
    const STATE_WAITING_LIST = 'waiting_list';
    const STATE_VERIFIED = 'verified';

    use OAuth2UserState;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     *
     * @Groups({"v1.account.current", "default"})
     */
    public ?int $id;

    /** @ORM\Column(type="string", unique=true, nullable=true) */
    public ?string $username = null;

    /**
     * @var Collection|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Follow\Follow", mappedBy="user")
     */
    public Collection $followers;

    /**
     * @ORM\Column(type="string", length=180, nullable=true)
     *
     * @Groups({"v1.account.current"})
     */
    public ?string $email = null;

    /**
     * @var string|null
     * @Groups({"v1.account.current", "api.v1.video_room.history"})
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $name = null;

    /**
     * @var string|null
     * @Groups({"v1.account.current", "api.v1.video_room.history"})
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $surname = null;

    /**
     * @var string|null
     * @Groups({"v1.account.current"})
     * @ORM\Column(type="text", nullable=true)
     */
    public ?string $about = null;

    /**
     * @var PhoneNumber|null
     * @Groups({"v1.account.current"})
     * @ORM\Column(type="phone_number", nullable=true)
     */
    public ?PhoneNumber $phone = null;

    /**
     * @var UserPhoto|null
     * @ORM\OneToOne(targetEntity="App\Entity\Photo\UserPhoto", inversedBy="user", cascade={"all"})
     */
    public ?UserPhoto $avatar = null;

    /**
     * @var Community[]|Collection
     * @ORM\OneToMany(targetEntity="App\Entity\Community\CommunityParticipant", mappedBy="user")
     */
    public Collection $joinedCommunities;

    /**
     * @var ClubParticipant[]|ArrayCollection
     * @ORM\OneToMany(targetEntity=ClubParticipant::class, mappedBy="user")
     */
    public Collection $clubParticipants;

    /**
     * @var Role[]|ArrayCollection
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\Role",
     *     mappedBy="user",
     *     indexBy="role",
     *     cascade={"all"},
     *     orphanRemoval=true
     * )
     */
    public Collection $roles;

    /**
     * @var Country|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Location\Country")
     */
    public ?Country $country = null;

    /**
     * @var City|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Location\City")
     */
    public ?City $city = null;

    /**
     * @var Collection|ArrayCollection|Device[]
     * @ORM\OneToMany(targetEntity="App\Entity\User\Device", mappedBy="user", cascade={"all"})
     */
    public Collection $devices;

    /**
     * @var int
     * @Groups({"v1.account.current"})
     * @ORM\Column(type="bigint")
     */
    public int $createdAt;

    /**
     * @var User|null
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public ?User $referer = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $source = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $utmCompaign = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $utmSource = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $utmContent = null;

    /** @ORM\Column(type="datetime", nullable=true) */
    public ?\DateTime $deletedAt = null;

    /**
     * @var AccessToken[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\OAuth\AccessToken", mappedBy="user")
     */
    public Collection $accessTokens;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="App\Entity\Interest\Interest",
     *     cascade={"persist"}
     * )
     */
    public Collection $interests;

    /** @ORM\ManyToMany(targetEntity="App\Entity\Matching\Industry", cascade="persist") */
    public Collection $industries;

    /** @ORM\ManyToMany(targetEntity="App\Entity\Matching\Goal", cascade="persist") */
    public Collection $goals;

    /** @ORM\ManyToMany(targetEntity="App\Entity\Matching\Skill", cascade="persist") */
    public Collection $skills;

    /** @ORM\OneToOne(targetEntity="App\Entity\Invite\Invite", mappedBy="registeredUser") */
    public ?Invite $invite = null;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $bannedAt = null;

    /** @ORM\Column(type="string", options={"default": User::STATE_OLD_USER}) */
    public string $state = self::STATE_OLD_USER;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $recommendedForFollowingPriority = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $lastTimeActivity = null;

    /** @ORM\Column(type="boolean", nullable=true) */
    public ?bool $onlineInVideoRoom = null;

    /** @ORM\Column(type="integer", options={"default": 20}) */
    public int $freeInvites = 20;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $readNotificationNewInvites = false;

    /** @ORM\Column(type="integer", nullable=true) */
    public ?int $skipNotificationUntil = null;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    public bool $onBoardingNotificationAlreadySend = false;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $lastContactHash = null;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $isTester = false;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $uploadToElasticSearchAt = null;

    /** @ORM\Column(type="integer", options={"default": 0}) */
    public int $lockContactsUpload = 0;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $languages = ['EN'];

    /** @ORM\ManyToMany(targetEntity="App\Entity\User\Language") */
    public ?Collection $nativeLanguages;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $stripeCustomerId = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $oldPhoneNumber = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $deleted = null;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $isHost = false;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $alwaysShowOngoingUpcomingEvents = false;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $banComment = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $deleteComment = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public ?User $bannedBy = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public ?User $deletedBy = null;

    /**
     * @ORM\OneToMany(targetEntity=Subscription::class, mappedBy="author", orphanRemoval=true)
     */
    public Collection $subscriptions;

    /**
     * @ORM\OneToMany(targetEntity=PaidSubscription::class, mappedBy="subscriber")
     * @var Collection|PaidSubscription[]
     */
    public Collection $paidSubscriptions;

    /** @ORM\Column(type="json", nullable=true)  */
    public ?array $badges = [];

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $shortBio = null;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $longBio = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $twitter = null;

    /** @ORM\Column(type="string", nullable=true, unique=true) */
    public ?string $wallet = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $linkedin = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $instagram = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $deleteNewBadgeAt = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Club\Club") */
    public ?Club $registeredByClubLink = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $inviteCode = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $intercomId = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $intercomHash = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $metaMaskNonce = null;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $registerByInviteCode = null;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $registerByInviteCodeNotificationSend = false;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $telegramId = null;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $isTesterAuthorizationPushes = false;

    /**
     * User constructor.
     */
    public function __construct()
    {
        $this->joinedCommunities = new ArrayCollection();
        $this->accessTokens = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->devices = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->interests = new ArrayCollection();
        $this->industries = $this->goals = $this->skills = new ArrayCollection();
        $this->createdAt = $this->createdAt ?? time();
        $this->subscriptions = new ArrayCollection();
        $this->paidSubscriptions = new ArrayCollection();
        $this->nativeLanguages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @Groups({"v1.account.current"})
     */
    public function getAvatarSrc($width = ':WIDTH', $height = ':HEIGHT'): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        return $this->avatar->getResizerUrl($width, $height);
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles->map(fn(Role $role) => 'ROLE_'.mb_strtoupper($role->role))->toArray();

        $roles[] = 'ROLE_USER';
        $roles[] = 'ROLE_USER_'.mb_strtoupper($this->state);

        return array_unique($roles);
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return '';
    }

    /**
     * @see UserInterface
     *
     * @return string|null
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFullNameOrId(bool $short = false): string
    {
        if ($this->deleted !== null) {
            return 'Deleted User';
        }

        $result = (string) $this->id;
        if ($this->name && $this->surname) {
            if ($short) {
                $result = $this->name . ' ' . mb_substr($this->surname, 0, 1) . '.';
            } else {
                $result = $this->name.' '.$this->surname;
            }
        } elseif ($this->name) {
            $result = $this->name;
        } elseif ($this->surname) {
            $result = $this->surname;
        }

        return $result;
    }

    public function getFullNameOrUsername(): string
    {
        if ($this->deleted !== null) {
            return 'Deleted User';
        }

        $result = (string) $this->username;

        if ($this->name && $this->surname) {
            $result = $this->name.' '.$this->surname;
        } elseif ($this->name) {
            $result = $this->name;
        } elseif ($this->surname) {
            $result = $this->surname;
        }

        return $result;
    }

    public function addInterest(Interest $interest): self
    {
        if (!$this->interests->contains($interest)) {
            $this->interests->add($interest);
        }

        return $this;
    }

    public function addNativeLanguage(Language $language): self
    {
        $this->addLanguage($language->code);

        if (!$this->nativeLanguages->contains($language)) {
            $this->nativeLanguages->add($language);
        }

        $this->recalculateLanguages();

        return $this;
    }

    public function clearInterests(): self
    {
        $this->interests->clear();
        $this->recalculateLanguages();

        return $this;
    }

    /** @phpstan-impure */
    public function addLanguage(string $language): self
    {
        $this->languages[] = mb_strtoupper($language);
        $this->languages = array_unique($this->languages);

        return $this;
    }

    public function equals(User $user): bool
    {
        return $this->id && $user->id && $this->id == $user->id;
    }

    public function getEntityCode(): string
    {
        return 'user';
    }

    public function isHasFollower(User $user): bool
    {
        /** @phpstan-ignore-next-line */
        return !$this->followers->matching(Criteria::create()->where(
            Criteria::expr()->eq('follower', $user)
        ))->isEmpty();
    }

    public function isOnline(): bool
    {
        return $this->onlineInVideoRoom || time() - $this->lastTimeActivity < ConnectClub::ONLINE_USER_ACTIVITY_LIMIT;
    }

    public function getTimeZoneDifferenceWithUTC(): int
    {
        /** @var Device|null $lastDevice */
        /** @phpstan-ignore-next-line */
        $lastDevice = $this->devices->matching(Criteria::create()->orderBy(['createdAt' => Criteria::DESC]))->first();

        return $lastDevice ? $lastDevice->getTimeZoneDifferenceWithUTCInMinutes() * 60 : 0;
    }

    public function getPhoneNumberRegion(): string
    {
        if ($this->phone) {
            $region = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($this->phone);
        }

        return $region ?? PhoneNumberUtil::UNKNOWN_REGION;
    }

    public function isVerified(): bool
    {
        return $this->state === self::STATE_VERIFIED;
    }

    public function isVerifiedOrInvited(): bool
    {
        return in_array($this->state, [User::STATE_VERIFIED, User::STATE_INVITED]);
    }

    public function addRole(string $role): void
    {
        if ($this->roles->get($role)) {
            return;
        }

        $this->roles->add(new Role($this, $role));
    }

    public function removeRole(string $role): void
    {
        $this->roles->remove($role);
    }

    public function hasRole(string $role): bool
    {
        return (bool) $this->roles->get($role);
    }

    private function recalculateLanguages()
    {
        $this->languages = [];

        /** @var Language $nativeLanguage */
        foreach ($this->nativeLanguages as $nativeLanguage) {
            $this->addLanguage($nativeLanguage->code);
        }

        if (!$this->languages) {
            $this->addLanguage('EN');
        }
    }
}
