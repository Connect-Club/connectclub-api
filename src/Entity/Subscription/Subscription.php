<?php

namespace App\Entity\Subscription;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Subscription\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=SubscriptionRepository::class)
 * @ORM\Table(
 *     uniqueConstraints={
 *         @UniqueConstraint(name="active_subscription", columns={"author_id"}, options={"where":"(is_active = true)"})
 *     }
 * )
 */
class Subscription
{
    public const CURRENCY = 'USD';

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    public string $stripeId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    public string $stripePriceId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    public string $name;

    /**
     * @ORM\Column(type="string", length=1000, nullable=true)
     */
    public ?string $description;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="subscriptions")
     * @ORM\JoinColumn(nullable=false)
     */
    public User $author;

    /**
     * @ORM\Column(type="integer")
     */
    public int $price;

    /**
     * @ORM\Column(type="bigint")
     */
    public int $createdAt;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    public bool $isActive = false;

    /**
     * @var ArrayCollection|VideoRoom[]
     * @ORM\OneToMany(targetEntity=VideoRoom::class, mappedBy="subscription")
     */
    public Collection $videoRooms;

    /**
     * @ORM\OneToMany(targetEntity=PaidSubscription::class, mappedBy="subscription")
     */
    public Collection $subscribers;

    public function __construct(string $name, int $price, string $stripeId, string $stripePriceId, User $author)
    {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->price = $price;
        $this->stripeId = $stripeId;
        $this->author = $author;
        $this->createdAt = time();
        $this->stripePriceId = $stripePriceId;
        $this->subscribers = new ArrayCollection();
        $this->videoRooms = new ArrayCollection();
        $this->description = '';
    }

    /**
     * @return int[] Prices in cents
     */
    public static function getPriceChoices(): array
    {
        return [
            500,
            1000,
            1500,
            2000
        ];
    }
}
