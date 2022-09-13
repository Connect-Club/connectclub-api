<?php

namespace App\Tests\Fixture;

use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VideoRoomSubscriptionFixture extends Fixture
{
    private bool $isSubscriptionActive;

    public function __construct(bool $isSubscriptionActive = false)
    {
        $this->isSubscriptionActive = $isSubscriptionActive;
    }

    public function load(ObjectManager $manager): void
    {
        $userRepository = $manager->getRepository(User::class);

        $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

        $subscription = new Subscription(
            'Paid subscription',
            500,
            'stripe-id',
            'stripe-price-id',
            $main
        );
        $subscription->isActive = $this->isSubscriptionActive;
        $manager->persist($subscription);

        $videoRoomRepository = $manager->getRepository(VideoRoom::class);
        $videoRoom = $videoRoomRepository->findOneByName(BaseCest::VIDEO_ROOM_BOB_NAME);
        $videoRoom->subscription = $subscription;

        $manager->flush();
    }
}
