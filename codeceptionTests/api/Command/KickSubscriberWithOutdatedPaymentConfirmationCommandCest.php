<?php

namespace App\Tests\Command;

use App\Command\KickSubscribersWithOutdatedPaymentConfirmationCommand;
use App\Entity\Community\Community;
use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Kernel;
use App\Service\JitsiEndpointManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Mockery\MockInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class KickSubscriberWithOutdatedPaymentConfirmationCommandCest extends BaseCest
{
    /** @var JitsiEndpointManager|MockInterface  */
    private MockInterface $jitsiManager;

    /** @noinspection PhpSignatureMismatchDuringInheritanceInspection */
    //phpcs:ignore
    public function _before(ApiTester $I): void
    {
        parent::_before();

        $this->jitsiManager = Mockery::mock(JitsiEndpointManager::class);
        $I->mockService(JitsiEndpointManager::class, $this->jitsiManager);
    }

    public function testExecute(ApiTester $I): void
    {
        ClockMock::withClockMock(100);

        $I->loadFixtures(new class extends Fixture {
            private EntityManager $manager;

            public function load(ObjectManager $manager): void
            {
                $this->manager = $manager;

                $userRepository = $manager->getRepository(User::class);

                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $subscription = new Subscription(
                    'Subscription',
                    500,
                    'stripe-id',
                    'price-id',
                    $bob
                );
                $subscription->isActive = true;
                $manager->persist($subscription);

                $subscriptionWithoutRoom = new Subscription(
                    'Subscription without room',
                    500,
                    'stripe-id-2',
                    'price-id-2',
                    $this->createUser('Subscription author')
                );
                $subscriptionWithoutRoom->isActive = true;
                $manager->persist($subscriptionWithoutRoom);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Incomplete'),
                    $subscription,
                    PaidSubscription::STATUS_INCOMPLETE
                );
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Incomplete waiting outdated'),
                    $subscription,
                    PaidSubscription::STATUS_INCOMPLETE
                );
                $paidSubscription->waitingForPaymentConfirmationUpTo = 50;
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Incomplete waiting'),
                    $subscription,
                    PaidSubscription::STATUS_INCOMPLETE
                );
                $paidSubscription->waitingForPaymentConfirmationUpTo = 150;
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Active'),
                    $subscription,
                    PaidSubscription::STATUS_ACTIVE
                );
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Active waiting outdated'),
                    $subscription,
                    PaidSubscription::STATUS_ACTIVE
                );
                $paidSubscription->waitingForPaymentConfirmationUpTo = 50;
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Incomplete expired waiting'),
                    $subscription,
                    PaidSubscription::STATUS_INCOMPLETE_EXPIRED
                );
                $paidSubscription->waitingForPaymentConfirmationUpTo = 150;
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription(
                    $this->createUser('Active without room'),
                    $subscriptionWithoutRoom,
                    PaidSubscription::STATUS_ACTIVE
                );
                $paidSubscription->waitingForPaymentConfirmationUpTo = 150;
                $manager->persist($paidSubscription);

                $community = $manager->getRepository(Community::class)
                    ->findOneBy(['name' => BaseCest::VIDEO_ROOM_BOB_NAME]);

                $community->videoRoom->subscription = $subscription;

                $manager->flush();
            }

            private function createUser(string $name): User
            {
                $user = new User();
                $user->name = $name;
                $user->state = User::STATE_VERIFIED;

                $this->manager->persist($user);

                return $user;
            }
        });

        $this->assertWillBeKicked('Incomplete waiting outdated');
        $this->assertWillBeKicked('Incomplete expired waiting');

        $commandTester = new CommandTester($this->getCommand($I));
        $commandTester->execute([
            '--iteration-size' => 3
        ]);

        $I->assertEquals(0, $commandTester->getStatusCode());

        /** @var Subscription $subscription */
        $subscription = $I->grabEntityFromRepository(Subscription::class, [
            'name' => 'Subscription',
        ]);

        /** @var Subscription $subscriptionWithoutRoom */
        $subscriptionWithoutRoom = $I->grabEntityFromRepository(Subscription::class, [
            'name' => 'Subscription without room',
        ]);

        $this->assertNotWaitingForPayment($I, $this->findUser($I, 'Incomplete'), $subscription);
        $this->assertNotWaitingForPayment($I, $this->findUser($I, 'Incomplete waiting outdated'), $subscription);
        $this->assertWaitingForPayment($I, $this->findUser($I, 'Incomplete waiting'), $subscription);
        $this->assertNotWaitingForPayment($I, $this->findUser($I, 'Active'), $subscription);
        $this->assertNotWaitingForPayment($I, $this->findUser($I, 'Active waiting outdated'), $subscription);
        $this->assertNotWaitingForPayment($I, $this->findUser($I, 'Incomplete expired waiting'), $subscription);
        $this->assertNotWaitingForPayment($I, $this->findUser($I, 'Active without room'), $subscriptionWithoutRoom);
    }

    private function assertWaitingForPayment(ApiTester $I, User $user, Subscription $subscription): void
    {
        $paidSubscription = $I->grabEntityFromRepository(PaidSubscription::class, [
            'subscriber' => $user,
            'subscription' => $subscription,
        ]);

        $I->assertNotEmpty($paidSubscription->waitingForPaymentConfirmationUpTo);
    }

    private function assertNotWaitingForPayment(ApiTester $I, User $user, Subscription $subscription): void
    {
        $paidSubscription = $I->grabEntityFromRepository(PaidSubscription::class, [
            'subscriber' => $user,
            'subscription' => $subscription,
        ]);

        $I->assertNull($paidSubscription->waitingForPaymentConfirmationUpTo);
    }

    private function findUser(ApiTester $I, string $name): User
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @var User $user */
        $user = $I->grabEntityFromRepository(User::class, [
            'name' => $name,
        ]);

        return $user;
    }

    private function assertWillBeKicked(string $name): void
    {
        $this->jitsiManager->shouldReceive('disconnectUserFromRoom')
            ->withArgs(fn(User $actualUser) => $actualUser->name === $name)
            ->once();
    }

    private function getCommand(ApiTester $I): KickSubscribersWithOutdatedPaymentConfirmationCommand
    {
        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);

        return $application->find('app:kick-subscribers-with-outdated-payment-confirmation');
    }
}
