<?php

namespace App\Entity\Invite;

use App\Entity\Club\Club;
use App\Entity\User;
use App\Repository\Invite\InviteRepository;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=InviteRepository::class)
 * @ORM\Table(
 *     indexes={@ORM\Index(name="invite_phone_number", columns={"phone_number"})}
 * )
 */
class Invite
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $author;

    /** @ORM\OneToOne(targetEntity="App\Entity\User", inversedBy="invite") */
    public ?User $registeredUser = null;

    /** @ORM\Column(type="phone_number", nullable=true) */
    public ?PhoneNumber $phoneNumber = null;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /** @ORM\ManyToOne(targetEntity=Club::class) */
    public ?Club $club = null;

    public function __construct(User $author, ?PhoneNumber $phoneNumber = null)
    {
        $this->id = Uuid::uuid4();
        $this->author = $author;
        $this->phoneNumber = $phoneNumber;
        $this->createdAt = time();
    }
}
