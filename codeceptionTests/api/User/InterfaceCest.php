<?php

namespace App\Tests\User;

use App\Entity\Activity\NewUserFromWaitingListActivity;
use App\Entity\Community\Community;
use App\Entity\Follow\Follow;
use App\Entity\Invite\Invite;
use App\Entity\User\PhoneContact;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;

class InterfaceCest extends BaseCest
{
    public function testInterface(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $util = PhoneNumberUtil::getInstance();
                $manager->persist(
                    new PhoneContact($main, '+79636417683', $util->parse('+79636417683'), 'Vitya')
                );
                $manager->persist(
                    new PhoneContact($main, '+79636417684', $util->parse('+79636417684'), 'Masha')
                );
                $manager->persist(
                    new PhoneContact($main, '+79636417683', $util->parse('+79636417685'), 'Sergey')
                );
                $manager->persist(
                    new PhoneContact($main, '+79636417683', $util->parse('+79636417686'), 'Vadim')
                );

                $receptionRoom = new Community($bob, '61234f2c635a2');
                $receptionRoom->videoRoom->isReception = true;
                $receptionRoom->password = 'DGmg2JxO9AUDPRAl';
                $manager->persist($receptionRoom);

                $manager->persist(new Invite($main, $util->parse('+79636417686')));
                $manager->persist(new Invite($main, $util->parse('+79636417685')));
                $manager->persist(new Invite($main, $util->parse('+79636417684')));

                $main->readNotificationNewInvites = false;
                $manager->persist($main);

                $manager->persist(new NewUserFromWaitingListActivity($util->parse('+79636417686'), $main, $alice));
                $manager->persist(new NewUserFromWaitingListActivity($util->parse('+79636417686'), $main, $bob));

                $alice->onlineInVideoRoom = true;
                $alice->lastTimeActivity = time();
                $manager->persist($alice);
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                $activity = new NewUserFromWaitingListActivity($util->parse('+79636417686'), $main, $mike);
                $activity->readAt = time();
                $manager->persist($activity);

                $en = $manager->getRepository('App:User\Language')->findOneBy(['code' => 'EN']);
                $ru = $manager->getRepository('App:User\Language')->findOneBy(['code' => 'RU']);

                $alice->addNativeLanguage($ru);
                $manager->persist($alice);

                $main->addNativeLanguage($en);
                $manager->persist($main);

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/interface');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'communityLink' => 'https://t.me/connect_club_chat',
            ],
        ]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/interface');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'hasNewInvites' => true,
                'countNewActivities' => 2,
                'countFreeInvites' => 20,
                'countPendingInvites' => 3,
                'countOnlineFriends' => 1,
                'showFestivalBanner' => false,
                'checkInRoomId' => '61234f2c635a2',
                'checkInRoomPass' => 'DGmg2JxO9AUDPRAl',
                'communityLink' => 'https://t.me/connect_club_eng',
            ],
        ]);
        $I->assertArrayHasKey('joinDiscordLink', $I->grabDataFromResponseByJsonPath('$.response')[0]);

        $I->sendPost('/v1/interface/read-notification-new-invites');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v1/interface');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'hasNewInvites' => false,
                'countNewActivities' => 2,
                'countFreeInvites' => 20,
                'countPendingInvites' => 3,
                'countOnlineFriends' => 1,
                'showFestivalBanner' => false,
                'checkInRoomId' => '61234f2c635a2',
                'checkInRoomPass' => 'DGmg2JxO9AUDPRAl'
            ],
        ]);
    }
}
