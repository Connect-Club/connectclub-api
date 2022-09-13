<?php

namespace App\Entity\User;

use App\Entity\User\PhoneContact;
use App\Repository\User\PhoneContactNumberRepository;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Entity(repositoryClass=PhoneContactNumberRepository::class)
 * @ORM\Table(
 *     indexes={@ORM\Index(name="phone_number", columns={"phone_number"})},
 *     uniqueConstraints={
 *         @UniqueConstraint(name="phone_contact_id_phone_number_unique", columns={"phone_contact_id", "phone_number"})
 *     }
 * )
 */
class PhoneContactNumber
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User\PhoneContact", inversedBy="phoneNumbers") */
    public PhoneContact $phoneContact;

    /** @ORM\Column(type="phone_number") */
    public PhoneNumber $phoneNumber;

    /** @ORM\Column(type="string") */
    public string $originalPhone;

    public function __construct(PhoneContact $phoneContact, string $originalPhone, PhoneNumber $phoneNumber)
    {
        $this->id = Uuid::uuid4();
        $this->phoneContact = $phoneContact;
        $this->originalPhone = $originalPhone;
        $this->phoneNumber = $phoneNumber;
    }
}
