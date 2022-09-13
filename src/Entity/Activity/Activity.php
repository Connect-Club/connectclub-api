<?php

namespace App\Entity\Activity;

use App\Entity\User;
use App\Repository\Activity\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\MappedSuperclass()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     Activity::TYPE_NEW_USER_ASK_INVITE: NewUserFromWaitingListActivity::class,
 *     Activity::TYPE_USER_SCHEDULE_EVENT: ScheduledEventMeetingActivity::class,
 *     Activity::TYPE_USER_REGISTERED: UserRegisteredActivity::class,
 *     Activity::TYPE_VIDEO_ROOM_STARTED: StartedVideoRoomActivity::class,
 *     Activity::TYPE_REGISTERED_AS_CO_HOST: RegisteredAsCoHostActivity::class,
 *     Activity::TYPE_NEW_FOLLOWER: NewFollowerActivity::class,
 *     Activity::TYPE_INVITE_PRIVATE_VIDEO_ROOM: InvitePrivateVideoRoomActivity::class,
 *     Activity::TYPE_WELCOME_ON_BOARDING_FRIEND: WelcomeOnBoardingFriendActivity::class,
 *     Activity::TYPE_INVITE_ON_BOARDING: InviteWelcomeOnBoardingActivity::class,
 *     Activity::TYPE_INTRO: IntroActivity::class,
 *     Activity::TYPE_CUSTOM: CustomActivity::class,
 *     Activity::TYPE_JOIN_DISCORD_COMMUNITY: JoinDiscordActivity::class,
 *     Activity::TYPE_JOIN_TELEGRAM_COMMUNITY: JoinTelegramCommunityLinkActivity::class,
 *     Activity::TYPE_CONNECT_YOU_BACK: ConnectYouBackActivity::class,
 *     Activity::TYPE_JOIN_REQUEST_WAS_APPROVED: JoinRequestWasApprovedActivity::class,
 *     Activity::TYPE_NEW_JOIN_REQUEST: NewJoinRequestActivity::class,
 *     Activity::TYPE_CLUB_VIDEO_ROOM_STARTED: StartedClubVideoRoomActivity::class,
 *     Activity::TYPE_USER_CLUB_SCHEDULE_EVENT: ClubScheduledEventMeetingActivity::class,
 *     Activity::TYPE_USER_CLUB_SCHEDULE_REGISTERED_AS_CO_HOST: ClubRegisteredAsCoHostActivity::class,
 *     Activity::TYPE_REGISTERED_AS_SPEAKER: RegisteredAsSpeakerActivity::class,
 *     Activity::TYPE_NEW_USER_REGISTERED_BY_INVITE_CODE: NewUserRegisteredByInviteCodeActivity::class,
 *     Activity::TYPE_NEW_CLUB_INVITE: NewClubInviteActivity::class,
 *     Activity::TYPE_APPROVED_PRIVATE_MEETING: ApprovedPrivateMeetingActivity::class,
 *     Activity::TYPE_ARRANGED_PRIVATE_MEETING: ArrangedPrivateMeetingActivity::class,
 *     Activity::TYPE_CHANGED_PRIVATE_MEETING: ChangedPrivateMeetingActivity::class,
 *     Activity::TYPE_CANCELLED_PRIVATE_MEETING: CancelledPrivateMeetingActivity::class,
 * })
 * @ORM\Entity(repositoryClass=ActivityRepository::class)
 */
abstract class Activity implements ActivityInterface
{
    const TYPE_NEW_USER_ASK_INVITE = 'new-user-ask-invite';
    const TYPE_USER_SCHEDULE_EVENT = 'user-schedule-event';
    const TYPE_USER_CLUB_SCHEDULE_EVENT = 'user-club-schedule-event';
    const TYPE_USER_CLUB_SCHEDULE_REGISTERED_AS_CO_HOST = 'user-club-registered-as-co-host';
    const TYPE_USER_REGISTERED = 'user-registered';
    const TYPE_VIDEO_ROOM_STARTED = 'video-room-started';
    const TYPE_REGISTERED_AS_CO_HOST = 'registered-as-co-host';
    const TYPE_NEW_FOLLOWER = 'new-follower';
    const TYPE_CONNECT_YOU_BACK = 'connect-you-back';
    const TYPE_INVITE_PRIVATE_VIDEO_ROOM = 'invite-private-video-room';
    const TYPE_WELCOME_ON_BOARDING_FRIEND = 'welcome-on-boarding-friend';
    const TYPE_INVITE_ON_BOARDING = 'invite-on-boarding';
    const TYPE_INTRO = 'intro';
    const TYPE_CUSTOM = 'custom';
    const TYPE_JOIN_REQUEST_WAS_APPROVED = 'join-request-was-approved';
    const TYPE_NEW_JOIN_REQUEST = 'new-join-request';
    const TYPE_CLUB_VIDEO_ROOM_STARTED = 'club-video-room-started';
    const TYPE_JOIN_TELEGRAM_COMMUNITY = 'join-telegram-community';
    const TYPE_JOIN_DISCORD_COMMUNITY = 'join-discord';
    const TYPE_REGISTERED_AS_SPEAKER = 'registered-as-speaker';
    const TYPE_NEW_USER_REGISTERED_BY_INVITE_CODE = 'new-user-registered-by-invite-code';
    const TYPE_NEW_CLUB_INVITE = 'new-club-invite';
    const TYPE_APPROVED_PRIVATE_MEETING = 'approved-private-meeting';
    const TYPE_ARRANGED_PRIVATE_MEETING = 'arranged-private-meeting';
    const TYPE_CHANGED_PRIVATE_MEETING = 'changed-private-meeting';
    const TYPE_CANCELLED_PRIVATE_MEETING = 'cancelled-private-meeting';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User", fetch="EAGER") */
    public ?User $user = null;

    /**
     * @var User[]|Collection
     * @ORM\ManyToMany(targetEntity="App\Entity\User", fetch="EAGER")
     */
    public Collection $nestedUsers;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $readAt = null;

    public function __construct(User $user, User ...$users)
    {
        $this->id = Uuid::uuid4();
        $this->user = $user;
        $this->nestedUsers = new ArrayCollection();
        array_map(fn(User $user) => $this->nestedUsers->add($user), $users);
        $this->createdAt = time();
    }

    public function __clone()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getNestedUsers(): Collection
    {
        return $this->nestedUsers;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?int
    {
        return $this->readAt;
    }

    public function setReadAt(?int $readAt): void
    {
        $this->readAt = $readAt;
    }
}
