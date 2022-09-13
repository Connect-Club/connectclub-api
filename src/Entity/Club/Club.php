<?php

namespace App\Entity\Club;

use App\Entity\Community\CommunityParticipant;
use App\Entity\Interest\Interest;
use App\Entity\Photo\Image;
use App\Entity\User;
use App\Repository\Club\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @ORM\Entity(repositoryClass=ClubRepository::class)
 */
class Club
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string", unique=true) */
    public string $title;

    /** @ORM\Column(type="string", unique=true) */
    public string $slug;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Photo\Image") */
    public ?Image $avatar = null;

    /**
     * @var ArrayCollection|ClubParticipant[]
     * @ORM\OneToMany(targetEntity="App\Entity\Club\ClubParticipant", mappedBy="club", cascade="all")
     */
    public Collection $participants;

    /** @ORM\ManyToMany(targetEntity="App\Entity\Interest\Interest") */
    public Collection $interests;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $description = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $owner;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $isPublic = false;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $togglePublicModeEnabled = false;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /** @ORM\Column(type="bigint") */
    public int $freeInvites;

    /** @ORM\Column(type="string", unique=true, nullable=true) */
    public ?string $invitationLink = null;

    public function __construct(User $owner, string $title)
    {
        $this->id = Uuid::uuid4();
        $this->invitationLink = Uuid::uuid4()->toString();
        $this->title = $title;
        $this->slug = $this->slugify($title);
        $this->owner = $owner;
        $this->participants = new ArrayCollection();
        $this->interests = new ArrayCollection();
        $this->participants->add(new ClubParticipant($this, $owner, $owner, ClubParticipant::ROLE_OWNER));
        $this->createdAt = time();
        $this->freeInvites = 1000;
    }

    public function getParticipant(User $user): ?ClubParticipant
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));

        $participant = $this->participants->matching($criteria)->first();

        return $participant ? $participant : null;
    }

    public function isParticipantRole(User $user, string $role): bool
    {
        $participant = $this->getParticipant($user);

        return $participant && $participant->role == $role;
    }

    public function addInterest(Interest $interest): self
    {
        if (!$this->interests->contains($interest)) {
            $this->interests->add($interest);
        }

        return $this;
    }

    private function slugify(string $str): string
    {
        $slugger = new AsciiSlugger();

        return mb_strtolower($slugger->slug($str, '-'));
    }
}
