<?php

namespace App\Entity\Community;

use App\ConnectClub;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Community\CommunityRepository")
 */
class Community
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     *
     * @Groups({
     *     "v1.community.default",
     * })
     */
    public ?int $id = null;

    /**
     * @var string
     * @ORM\Column(type="string", unique=true)
     *
     * @Groups({
     *     "v1.community.default",
     * })
     */
    public string $name;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"v1.room.create"})
     */
    public string $password;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     *
     * @Groups({
     *     "v1.community.default",
     * })
     */
    public User $owner;

    /**
     * @var VideoRoom
     * @ORM\OneToOne(targetEntity="App\Entity\VideoChat\VideoRoom", inversedBy="community", cascade="all")
     */
    public VideoRoom $videoRoom;

    /**
     * @var ArrayCollection|CommunityParticipant[]
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\Community\CommunityParticipant",
     *     mappedBy="community",
     *     cascade={"all"}
     * )
     */
    public Collection $participants;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     *
     * @Groups({
     *     "v1.community.default",
     * })
     */
    public ?string $description;

    /**
     * @var int
     * @ORM\Column(type="bigint")
     *
     * @Groups({
     *     "v1.community.default",
     * })
     */
    public int $createdAt;

    /** @ORM\Column(type="text", length=500, nullable=true) */
    public ?string $about;

    /**
     * Community constructor.
     */
    public function __construct(User $owner, string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->password = ConnectClub::generateString(16);
        $this->owner = $owner;
        $this->description = $description;
        $this->participants = new ArrayCollection();
        $this->createdAt = time();
        $this->videoRoom = new VideoRoom($this);

        $this->addParticipant($owner);

        $participantAdmin = $this->getParticipant($owner);
        $participantAdmin->role = CommunityParticipant::ROLE_ADMIN;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant(User $user): ?CommunityParticipant
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));

        $participant = $this->participants->matching($criteria)->first();

        return $participant ? $participant : null;
    }

    public function addParticipant(User $participant, string $role = CommunityParticipant::ROLE_MEMBER): self
    {
        if (!$this->getParticipant($participant)) {
            $this->participants->add(new CommunityParticipant($participant, $this, $role));
        }

        return $this;
    }

    public function removeParticipant(User $participant): self
    {
        if ($participant = $this->getParticipant($participant)) {
            $this->participants->removeElement($participant);
        }

        return $this;
    }

    public function isMuteFor(User $user): bool
    {
        return false;
    }

    public function isAdmin(User $user): bool
    {
        $participant = $this->getParticipant($user);

        return $participant && $participant->role == CommunityParticipant::ROLE_ADMIN;
    }

    public function isModerator(User $user): bool
    {
        $participant = $this->getParticipant($user);

        return $participant && $participant->role == CommunityParticipant::ROLE_MODERATOR;
    }
}
