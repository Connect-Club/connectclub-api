<?php

namespace App\Tests\User;

use App\Entity\Activity\Activity;
use App\Entity\Activity\ClubScheduledEventMeetingActivity;
use App\Entity\Activity\ConnectYouBackActivity;
use App\Entity\Activity\IntroActivity;
use App\Entity\Activity\InvitePrivateVideoRoomActivity;
use App\Entity\Activity\InviteWelcomeOnBoardingActivity;
use App\Entity\Activity\JoinDiscordActivity;
use App\Entity\Activity\JoinRequestWasApprovedActivity;
use App\Entity\Activity\JoinTelegramCommunityLinkActivity;
use App\Entity\Activity\NewFollowerActivity;
use App\Entity\Activity\NewJoinRequestActivity;
use App\Entity\Activity\NewUserFromWaitingListActivity;
use App\Entity\Activity\RegisteredAsCoHostActivity;
use App\Entity\Activity\RegisteredAsSpeakerActivity;
use App\Entity\Activity\ScheduledEventMeetingActivity;
use App\Entity\Activity\StartedClubVideoRoomActivity;
use App\Entity\Activity\StartedVideoRoomActivity;
use App\Entity\Activity\UserRegisteredActivity;
use App\Entity\Activity\WelcomeOnBoardingFriendActivity;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\JoinRequest;
use App\Entity\Event\EventSchedule;
use App\Entity\Follow\Follow;
use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;

class ActivityCest extends BaseCest
{
    public function testActivity(ApiTester $I)
    {
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture
        {
            public function load(ObjectManager $manager)
            {
                $country = new Country();
                $country->id = 5;
                $country->name = 'Russia';
                $country->continentCode = 'EU';
                $country->continentName = 'Europe';
                $country->isInEuropeanUnion = false;
                $country->isoCode = 'RU';
                $manager->persist($country);

                $city = new City();
                $city->id = 5;
                $city->country = $country;
                $city->timeZone = 'Europe/Moscow';
                $city->name = 'Moscow';
                $city->subdivisionFirstIsoCode = 'MOW';
                $city->subdivisionFirstName = 'MOW';
                $city->subdivisionSecondIsoCode = 'MOW';
                $city->subdivisionSecondName = 'MOW';
                $city->metroCode = 'R';
                $city->latitude = 1;
                $city->longitude = 0;
                $city->accuracyRadius = 0;
                $manager->persist($city);

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->city = $city;
                $manager->persist($main);

                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $phoneNumber = PhoneNumberUtil::getInstance()->parse('+79364832433');

                $eventSchedule = new EventSchedule($mike, 'Mike event schedule', 1615880935, '');
                $eventSchedule->id = Uuid::fromString('7598b12a-6c14-4987-be3d-1b1666a268c2');
                $manager->persist($eventSchedule);

                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')
                                     ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);
                $videoRoom->community->password = 'qwerty';
                $videoRoom->eventSchedule = $eventSchedule;
                $manager->persist($videoRoom->community);

                $videoRoomBob = $manager->getRepository('App:VideoChat\VideoRoom')
                                        ->findOneByName(BaseCest::VIDEO_ROOM_BOB_NAME);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $activity = new ConnectYouBackActivity($main, $alice);
                $activity->createdAt = time() + 9;
                $manager->persist($activity);
                $activity = new JoinTelegramCommunityLinkActivity($main);
                $activity->createdAt = time() + 10;
                $manager->persist($activity);
                $activity = new IntroActivity($main);
                $activity->createdAt = time() + 11;
                $manager->persist($activity);
                $activity = new InviteWelcomeOnBoardingActivity($videoRoom, $main, $mike);
                $activity->createdAt = time() + 12;
                $manager->persist($activity);
                $activity = new WelcomeOnBoardingFriendActivity($videoRoom, $main, $mike);
                $activity->createdAt = time() + 13;
                $manager->persist($activity);
                $activity = new InvitePrivateVideoRoomActivity($videoRoom, $main, $mike);
                $activity->createdAt = time() + 14;
                $manager->persist($activity);
                $activity = new NewFollowerActivity($main, $mike);
                $activity->createdAt = time() + 15;
                $manager->persist($activity);
                $activity = new StartedVideoRoomActivity($videoRoomBob, $main, $mike);
                $activity->createdAt = time() + 16;
                $manager->persist($activity);
                $activity = new RegisteredAsCoHostActivity($eventSchedule, $main, $mike);
                $activity->createdAt = time() + 19;
                $manager->persist($activity);
                $activity = new NewUserFromWaitingListActivity($phoneNumber, $main, $alice);
                $activity->createdAt = time() + 20;
                $manager->persist($activity);
                $activity = new NewUserFromWaitingListActivity($phoneNumber, $main, $bob);
                $activity->createdAt = time() + 21;
                $manager->persist($activity);
                $activity = new ScheduledEventMeetingActivity($eventSchedule, $main, $mike);
                $activity->createdAt = time() + 22;
                $manager->persist($activity);
                $activity = new ClubScheduledEventMeetingActivity($mainClub, $eventSchedule, $main, $mike);
                $activity->createdAt = time() + 23;
                $manager->persist($activity);
                $activity = new StartedVideoRoomActivity($videoRoom, $main, $mike);
                $activity->createdAt = time() + 24;
                $manager->persist($activity);
                $activity = new StartedClubVideoRoomActivity($mainClub, $videoRoom, $main, $mike);
                $activity->createdAt = time() + 25;
                $manager->persist($activity);
                $activity = new JoinRequestWasApprovedActivity($mainClub, ClubParticipant::ROLE_OWNER, $main, $mike);
                $activity->createdAt = time() + 26;
                $manager->persist($activity);

                $mikeJoinRequest = new JoinRequest($mainClub, $mike);
                $manager->persist($mikeJoinRequest);

                $activity = new NewJoinRequestActivity($mikeJoinRequest, $main, $mike);
                $activity->createdAt = time() + 27;
                $manager->persist($activity);

                $activity = new RegisteredAsSpeakerActivity($eventSchedule, $eventSchedule->club, $mike, $main);
                $activity->createdAt = time() + 28;
                $manager->persist($activity);

                $activity = new JoinDiscordActivity($main);
                $activity->createdAt = time() + 28;
                $manager->persist($activity);

                for ($i = 0; $i < 10; $i++) {
                    $user = new User();
                    $user->name = 'User-'.$i;
                    $user->surname = 'User-'.$i;
                    $user->username = 'user'.$i;

                    $manager->persist($user);

                    if ($i === 9) {
                        $manager->persist(new Follow($user, $main));
                    }

                    if ($i === 8) {
                        $manager->persist(new Follow($main, $user));
                    }

                    $activity = new UserRegisteredActivity($main, $user);
                    $activity->createdAt = time() + $i;

                    $manager->persist($activity);
                }

                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/activity?limit=15');
        $I->seeResponseCodeIs(HttpCode::OK);

        /** @var User $mike */
        $mike = $I->grabEntityFromRepository(User::class, [
            'email' => self::MIKE_USER_EMAIL,
        ]);

        /** @var Club $mainClub */
        $mainClub = $I->grabEntityFromRepository(Club::class, [
            'title' => 'Main Club',
        ]);

        /** @var JoinRequest $mikeJoinRequest */
        $mikeJoinRequest = $I->grabEntityFromRepository(JoinRequest::class, [
            'author' => $mike,
            'club' => $mainClub,
        ]);

        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'type' => 'join-discord',
                        'title' => 'Join our Discord & the discussion on how to build the Metaverse at Connect.Club',
                        'head' => 'Join the Discord',
                        'link' => 'https://discord.gg/FZWdCn7XZU',
                        'relatedUsers' => [],
                        'new' => true,
                    ],
                    0 => [
                        'type' => 'new-join-request',
                        'title' => "Mike M. wants to join “Main Club”. Tap to review 👉",
                        'head' => 'New request to join the club',
                        'joinRequestId' => $mikeJoinRequest->id->toString(),
                        'clubId' => $mikeJoinRequest->club->id->toString(),
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    1 => [
                        'type' => 'join-request-was-approved',
                        'title' => "Mike M. (creator) approved you request",
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    2 => [
                        'type' => 'club-video-room-started',
                        //phpcs:ignore
                        'title' => 'Mike M. is running a room in “Main Club”. Tap to take a look 👉 “Video room description”.',
                        'head' => 'A new room has started',
                        'roomId' => self::VIDEO_ROOM_TEST_NAME,
                        'roomPass' => 'qwerty',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    3 => [
                        'type' => 'video-room-started',
                        'title' => 'Tap to join 👉 “Video room description”',
                        'roomId' => self::VIDEO_ROOM_TEST_NAME,
                        'roomPass' => 'qwerty',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    4 => [
                        'type' => 'user-club-schedule-event',
                        'title' => 'Mike M. has organized “Mike event schedule” for “Main Club“ on Tuesday, March 16 at 10:48 AM. Tap to take a look 👉', //phpcs:ignore
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'date' => 1615880935,
                        'eventScheduleId' => '7598b12a-6c14-4987-be3d-1b1666a268c2',
                        'new' => true,
                    ],
                    5 => [
                        'type' => 'user-schedule-event',
                        'title' => 'Mike M. is going to talk about “Mike event schedule”. It starts on Tuesday, March 16 at 10:48 AM. Tap to take a look 👉',//phpcs:ignore
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'date' => 1615880935,
                        'eventScheduleId' => '7598b12a-6c14-4987-be3d-1b1666a268c2',
                        'new' => true,
                    ],
                    6 => [
                        'phone' => '+79364832433',
                        'type' => 'new-user-ask-invite',
                        //phpcs:ignore
                        'title' => 'One of your contacts wants to join Connect.Club. Tap to let them in 👉',
                        'head' => 'bob_user_name b. is on the waitlist',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'bob_user_name',
                                'surname' => 'bob_user_surname',
                                'displayName' => 'bob_user_name bob_user_surname',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    7 => [
                        'phone' => '+79364832433',
                        'type' => 'new-user-ask-invite',
                        //phpcs:ignore
                        'title' => 'One of your contacts wants to join Connect.Club. Tap to let them in 👉',
                        'head' => 'alice_user_name a. is on the waitlist',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'alice_user_name',
                                'surname' => 'alice_user_surname',
                                'displayName' => 'alice_user_name alice_user_surname',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    8 => [
                        'type' => 'registered-as-co-host',
                        //phpcs:ignore
                        'title' => 'Don’t miss out! You’ve been appointed as a moderator by Mike M. for “Mike event schedule” which starts on Tuesday, March 16 at 10:48 AM',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'date' => 1615880935,
                        'eventScheduleId' => '7598b12a-6c14-4987-be3d-1b1666a268c2',
                        'new' => true,
                    ],
                    9 => [
                        'type' => 'video-room-started',
                        'title' => 'Tap to join 👉',
                        'head' => 'Mike M. started a room',
                        'roomId' => self::VIDEO_ROOM_BOB_NAME,
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    10 => [
                        'type' => 'new-follower',
                        'title' => 'Mike M. wants to connect with you!',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    11 => [
                        'type' => 'invite-private-video-room',
                        'head' => 'Join the private room!',
                        //phpcs:ignore
                        'title' => 'Mike M. wants you to join the room and speak privately about “Video room description”. Tap to join 👉',
                        'roomId' => self::VIDEO_ROOM_TEST_NAME,
                        'roomPass' => 'qwerty',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    12 => [
                        'type' => 'welcome-on-boarding-friend',
                        'head' => 'Your contact joined Connect.Club',
                        //phpcs:ignore
                        'title' => 'Familiarize Mike M. with Connect.Club. Tap to go to a private room 👉',
                        'roomId' => self::VIDEO_ROOM_TEST_NAME,
                        'roomPass' => 'qwerty',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    13 => [
                        'type' => 'invite-on-boarding',
                        //phpcs:ignore
                        'title' => 'Mike M. wants to help you know more about Connect.Club. Tap to join them in the room! 👉',
                        'roomId' => self::VIDEO_ROOM_TEST_NAME,
                        'roomPass' => 'qwerty',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    14 => [
                        'type' => 'intro',
                        //phpcs:ignore
                        'title' => "It's great to see you on board, main_user_name\r\nLet's have a quick tour around to see what you can do in our club.",
                        'new' => true,
                    ],
                    15 => [
                        'type' => 'custom',
                        //phpcs:ignore
                        'title' => 'Join our community chat',
                        'body' => 'You can find link to community in your profile settings',
                        'externalLink' => 'https://t.me/connect_club_eng',
                        'new' => true,
                    ],
                    16 => [
                        'type' => 'connect-you-back',
                        //phpcs:ignore
                        'title' => 'alice_user_name a. connected you back.',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'alice_user_name',
                                'surname' => 'alice_user_surname',
                                'displayName' => 'alice_user_name alice_user_surname',
                                'about' => '',
                                'username' => '',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                    17 => [
                        'type' => 'user-registered',
                        //phpcs:ignore
                        'title' => 'User-9 U. is here! Do you want to connect them?',
                        'relatedUsers' => [
                            0 => [
                                'name' => 'User-9',
                                'surname' => 'User-9',
                                'displayName' => 'User-9 User-9',
                                'about' => '',
                                'username' => 'user9',
                                'isDeleted' => false,
                            ],
                        ],
                        'new' => true,
                    ],
                ],
                'lastValue' => null,
            ]
        ]);

        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNull($lastValue);
        $I->assertCount(28, $items);

        $lastActivityId = $I->grabDataFromResponseByJsonPath('$.response.items[0].id')[0];
        $I->sendPost('/v1/activity/'.$lastActivityId.'/read');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInRepository(Activity::class, ['readAt' => null, 'user' => ['email' => self::MAIN_USER_EMAIL]]);

        $I->sendGet('/v1/activity?limit=100');
        $I->seeResponseCodeIs(HttpCode::OK);
        $ids = array_map(fn(array $item) => $item['id'], $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->assertGreaterThan(0, $ids);

        foreach ($ids as $id) {
            $I->sendDelete('/v1/activity/'.$id);
            $I->seeResponseCodeIs(HttpCode::OK);
        }

        $I->sendGet('/v1/activity?limit=100');
        $I->seeResponseCodeIs(HttpCode::OK);
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(0, $items);
    }
}
