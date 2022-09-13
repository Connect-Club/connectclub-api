<?php

namespace App\Tests\Command;

use App\Entity\Invite\Invite;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Console\Tester\CommandTester;
use App\Kernel;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Symfony\Bundle\FrameworkBundle\Console\Application;

class AddNewInvitesCommandCest extends BaseCest
{
    public function testExecuteEmpty(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $main->freeInvites = 9;
                $alice->freeInvites = 0;
                $bob->freeInvites = 0;
                $mike->freeInvites = 0;

                $util = PhoneNumberUtil::getInstance();

                $invite = new Invite($alice, $util->parse('+79636417683'));
                $manager->persist($invite);

                $invite = new Invite($alice, $util->parse('+79636417682'));
                $invite->createdAt = time() - 86400 - 1; //24 hours 0 minutes 1 second old
                $manager->persist($invite);

                $invite = new Invite($bob, $util->parse('+79636417682'));
                $invite->createdAt = time() - 3600; //1 hours old
                $manager->persist($invite);

                $manager->persist($main);
                $manager->persist($alice);
                $manager->persist($bob);
                $manager->persist($mike);

                $manager->flush();
            }
        });

        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);

        $command = $application->find('AddNewInvitesCommand');

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);
        $mike = $I->grabEntityFromRepository(User::class, ['email' => self::MIKE_USER_EMAIL]);

        $I->assertEquals(20, $main->freeInvites);
        $I->assertEquals(20, $alice->freeInvites);
        $I->assertEquals(20, $bob->freeInvites);
        $I->assertEquals(0, $mike->freeInvites);
    }
}
