<?php

namespace App\Tests\Command;

use App\Command\RemoveExpiredNewBadgesCommand;
use App\Entity\User;
use App\Kernel;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RemoveExpiredNewBadgesCommandCest extends BaseCest
{
    public function testExecute(ApiTester $I): void
    {
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $alice->badges = ['old'];

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->badges = ['super-new', 'new', 'old'];
                $main->deleteNewBadgeAt = time() - 100;

                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $bob->badges = ['super-new', 'new', 'old'];
                $bob->deleteNewBadgeAt = time() + 100;

                $manager->flush();
            }
        });

        $commandTester = new CommandTester($this->getCommand($I));
        $commandTester->execute([]);

        $I->assertEquals(0, $commandTester->getStatusCode());

        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, [
            'email' => BaseCest::MAIN_USER_EMAIL,
            'deleteNewBadgeAt' => null,
        ]);
        $I->assertEquals(['super-new', 'old'], $main->badges);

        /** @var User $alice */
        $alice = $I->grabEntityFromRepository(User::class, [
            'email' => BaseCest::ALICE_USER_EMAIL,
            'deleteNewBadgeAt' => null,
        ]);
        $I->assertEquals(['old'], $alice->badges);

        /** @var User $bob */
        $bob = $I->grabEntityFromRepository(User::class, [
            'email' => BaseCest::BOB_USER_EMAIL,
            'deleteNewBadgeAt' => time() + 100,
        ]);
        $I->assertEquals(['super-new', 'new', 'old'], $bob->badges);
    }

    private function getCommand(ApiTester $I): RemoveExpiredNewBadgesCommand
    {
        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);

        return $application->find('app:remove-expired-new-badges');
    }
}
