<?php

namespace App\Entity\User;

use App\Entity\User\PhoneContactNumber;
use App\Entity\User;
use App\Repository\User\PhoneContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Entity(repositoryClass=PhoneContactRepository::class)
 * @ORM\Table(
 *     indexes={
 *         @ORM\Index(name="phone_contact_phone_number", columns={"phone_number"}),
 *     },
 *     uniqueConstraints={
 *         @UniqueConstraint(name="owner_id_phone_number_unique", columns={"owner_id", "phone_number"})
 *     }
 * )
 */
class PhoneContact
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $owner;

    /** @ORM\Column(type="phone_number") */
    public PhoneNumber $phoneNumber;

    /** @ORM\Column(type="string") */
    public string $originalPhone;

    /**
     * @var PhoneContactNumber[]|Collection
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\User\PhoneContactNumber",
     *     cascade="all",
     *     orphanRemoval=true,
     *     mappedBy="phoneContact"
     * )
     */
    public Collection $phoneNumbers;

    /** @ORM\Column(type="string") */
    public string $fullName;

    /** @ORM\Column(type="integer", options={"default": 0}) */
    public int $sort = 0;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $thumbnail = null;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(
        User $owner,
        string $originalPhoneNumber,
        PhoneNumber $phoneNumber,
        string $fullName,
        ?string $thumbnail = null
    ) {
        $this->id = Uuid::uuid4();
        $this->owner = $owner;
        $this->phoneNumber = $phoneNumber;
        $this->originalPhone = $originalPhoneNumber;
        $this->phoneNumbers = new ArrayCollection();
        $this->fullName = $fullName;
        $this->thumbnail = $thumbnail;
        $this->createdAt = time();

        $this->addAdditionalPhoneNumber(new PhoneContactNumber($this, $originalPhoneNumber, $phoneNumber));
    }

    public function addAdditionalPhoneNumber(PhoneContactNumber $phoneContactNumber): self
    {
        $alreadyExists = !$this->phoneNumbers->filter(
            fn(PhoneContactNumber $n) => $n->phoneNumber->equals($phoneContactNumber->phoneNumber)
        )->isEmpty();

        if (!$alreadyExists) {
            $this->phoneNumbers->add($phoneContactNumber);
        }

        return $this;
    }
}
