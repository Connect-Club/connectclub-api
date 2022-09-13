<?php

namespace App\Tests\VideoRoom;

use App\Controller\ErrorCode;
use App\Entity\Community\Community;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomEvent;
use App\Repository\Subscription\PaidSubscriptionRepository;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\VideoRoomSubscriptionFixture;
use App\Tests\Fixture\VideoRoomTokenFixture;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\PhpUnit\ClockMock;

class VideoRoomTokenCest extends BaseCest
{
    protected string $tokenUrl = '/v2/video-room/token/';
    const MAIN_MEETING_SID = '5ea687610dc2b';
    const SUCCESS_TOKEN_JSON_FORMAT = [
        'token' => 'string',
        'config' => [
            'id' => 'integer',
            'backgroundRoom' => [
                'id' => 'integer',
                'originalName' => 'string',
                'processedName' => 'string',
                'bucket' => 'string',
                'uploadAt' => 'integer',
                'width' => 'integer',
                'height' => 'integer',
                'originalUrl' => 'string',
                'resizerUrl' => 'string',
            ],
            'backgroundRoomWidthMultiplier' => 'integer',
            'backgroundRoomHeightMultiplier' => 'integer',
            'initialRoomScale' => 'integer',
            'minRoomZoom' => 'integer',
            'maxRoomZoom' => 'integer',
            'videoBubbleSize' => 'integer',
            'publisherRadarSize' => 'integer',
            'intervalToSendDataTrackInMilliseconds' => 'integer',
            'videoQuality' => [
                'width' => 'integer',
                'height' => 'integer',
            ],
            'dataTrackUrl' => 'string',
            'speakerLocation' => [
                'x' => 'integer',
                'y' => 'integer',
            ],
            'imageMemoryMultiplier' => 'float',
        ],
        'name' => 'string',
        'description' => 'string|null',
        'id' => 'integer',
        'sid' => 'string|null',
        'ownerId' => 'integer',
        'open' => 'boolean',
        'chatRoomName' => 'string|null',
    ];

    public function getTokenConflictVideoRoomMeetingExistsInAnotherServer(ApiTester $I)
    {
        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $videoRoom = $manager->getRepository('App:Community\Community')
                    ->findOneBy(['name' => BaseCest::VIDEO_ROOM_BOB_NAME])
                    ->videoRoom;

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $meeting = new VideoMeeting($videoRoom, uniqid(), time(), VideoRoomEvent::INITIATOR_JITSI);
                $participant = new VideoMeetingParticipant($meeting, $main, time());

                $manager->persist($meeting);
                $manager->persist($participant);

                $manager->flush();
            }
        }, true);
    }

    public function getTokenNotFoundVideoRoom(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $I->sendPOST($this->tokenUrl.'not_found_video_room', json_encode([
            'password' => 'password'
        ]));

        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson([
            'response' => null,
            'errors' => [
                ErrorCode::V1_VIDEO_ROOM_NOT_FOUND
            ]
        ]);
    }

    public function getTokenWrongPasswordVideoRoom(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => 'wrong_password'
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson([
            'response' => null,
            'errors' => [
                ErrorCode::V1_VIDEO_ROOM_INCORRECT_PASSWORD
            ]
        ]);
    }

    public function testGetTokenWithoutSubscription(ApiTester $I)
    {
        $this->assertUserHasAccess($I, self::ALICE_ACCESS_TOKEN);
    }

    public function testGetTokenWithSubscriptionPrivilegedUsers(ApiTester $I)
    {
        $I->loadFixtures(new VideoRoomSubscriptionFixture(true));

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);

                $videoRoom = $manager->getRepository(VideoRoom::class)
                    ->findOneByName(BaseCest::VIDEO_ROOM_BOB_NAME);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $videoRoom->community->addParticipant($main, CommunityParticipant::ROLE_MODERATOR);

                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
                $videoRoom->community->addParticipant($mike, CommunityParticipant::ROLE_ADMIN);

                $manager->flush();
            }
        });

        $this->assertUserHasAccess($I, self::BOB_ACCESS_TOKEN);
        $this->assertUserHasAccess($I, self::MAIN_ACCESS_TOKEN);
        $this->assertUserHasAccess($I, self::MIKE_ACCESS_TOKEN);
    }

    /**
     * @dataProvider activeStatusDataProvider
     */
    public function testGetTokenWithSubscriptionByPaidUser(ApiTester $I, Example $example)
    {
        $I->loadFixtures(new VideoRoomSubscriptionFixture(true));

        $I->loadFixtures(new class($example['status']) extends Fixture {
            private int $status;

            public function __construct(int $status)
            {
                $this->status = $status;
            }

            public function load(ObjectManager $manager): void
            {
                $subscription = $manager->getRepository(Subscription::class)->findOneBy([
                    'name' => 'Paid subscription',
                ]);

                $alice = $manager->getRepository(User::class)
                    ->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $manager->persist(new PaidSubscription($alice, $subscription, $this->status));

                $manager->flush();
            }
        });

        $this->assertUserHasAccess($I, self::ALICE_ACCESS_TOKEN);
    }

    public function testGetTokenWithSubscriptionByPaidUserWithoutConfirmation(ApiTester $I)
    {
        ClockMock::withClockMock(true);

        $I->loadFixtures(new VideoRoomSubscriptionFixture(true));

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $subscription = $manager->getRepository(Subscription::class)->findOneBy([
                    'name' => 'Paid subscription',
                ]);

                $alice = $manager->getRepository(User::class)
                    ->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $main = $manager->getRepository(User::class)
                    ->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $paidSubscription = new PaidSubscription($alice, $subscription, PaidSubscription::STATUS_INCOMPLETE);
                $paidSubscription->waitingForPaymentConfirmationUpTo = time() + 300;
                $manager->persist($paidSubscription);

                $paidSubscription = new PaidSubscription($main, $subscription, PaidSubscription::STATUS_INCOMPLETE);
                $paidSubscription->waitingForPaymentConfirmationUpTo = time() - 1;
                $manager->persist($paidSubscription);

                $manager->flush();
            }
        });

        $this->assertUserHasAccess($I, self::ALICE_ACCESS_TOKEN);
        $this->assertUserHasNoAccess($I, self::MAIN_ACCESS_TOKEN);
    }

    /**
     * @dataProvider notActiveStatusDataProvider
     */
    public function testGetTokenWithSubscriptionAccessDenied(ApiTester $I, Example $example): void
    {
        $I->loadFixtures(new VideoRoomSubscriptionFixture(true));

        $I->loadFixtures(new class($example['status']) extends Fixture {
            private int $status;

            public function __construct(int $status)
            {
                $this->status = $status;
            }

            public function load(ObjectManager $manager): void
            {
                $subscription = $manager->getRepository(Subscription::class)->findOneBy([
                    'name' => 'Paid subscription',
                ]);

                $alice = $manager->getRepository(User::class)
                    ->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $manager->persist(new PaidSubscription($alice, $subscription, $this->status));

                $manager->flush();
            }
        });

        $this->assertPaymentRequired($I, self::ALICE_ACCESS_TOKEN);
    }

    public function activeStatusDataProvider(): \Generator
    {
        yield [
            'status' => PaidSubscription::STATUS_ACTIVE,
        ];
        yield [
            'status' => PaidSubscription::STATUS_TRIALING,
        ];
        yield [
            'status' => PaidSubscription::STATUS_PAST_DUE,
        ];
    }

    public function notActiveStatusDataProvider(): \Generator
    {
        yield [
            'status' => PaidSubscription::STATUS_UNPAID,
        ];
        yield [
            'status' => PaidSubscription::STATUS_INCOMPLETE,
        ];
        yield [
            'status' => PaidSubscription::STATUS_INCOMPLETE_EXPIRED,
        ];
        yield [
            'status' => PaidSubscription::STATUS_CANCELED,
        ];
    }

    public function testGetTokenWithInactiveSubscription(ApiTester $I): void
    {
        $I->loadFixtures(new VideoRoomSubscriptionFixture());

        $this->assertUserHasAccess($I, self::ALICE_ACCESS_TOKEN);
    }

    public function testGetTokenPaymentRequired(ApiTester $I)
    {
        $I->loadFixtures(new VideoRoomSubscriptionFixture(true));

        $this->assertPaymentRequired($I, self::ALICE_ACCESS_TOKEN);
    }

    private function assertUserHasAccess(ApiTester $I, string $accessToken): void
    {
        $I->amBearerAuthenticated($accessToken);

        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $this->getRoomPassword($I, self::VIDEO_ROOM_BOB_NAME),
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEmpty($I->grabDataFromResponseByJsonPath('$.errors')[0]);
        $I->assertNotEmpty($I->grabDataFromResponseByJsonPath('$.response.token')[0]);
    }

    private function assertUserHasNoAccess(ApiTester $I, string $accessToken): void
    {
        $I->amBearerAuthenticated($accessToken);

        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $this->getRoomPassword($I, self::VIDEO_ROOM_BOB_NAME),
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->assertNotEmpty($I->grabDataFromResponseByJsonPath('$.errors')[0]);
        $I->assertNull($I->grabDataFromResponseByJsonPath('$.response')[0]);
    }

    private function assertPaymentRequired(ApiTester $I, string $accessToken): void
    {
        $I->amBearerAuthenticated($accessToken);

        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $this->getRoomPassword($I, self::VIDEO_ROOM_BOB_NAME),
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson([
            'response' => null,
            'errors' => [
                ErrorCode::V1_VIDEO_ROOM_PAYMENT_REQUIRED
            ]
        ]);
    }

    private function getRoomPassword(ApiTester $I, string $roomName): ?string
    {
        return $I->grabFromRepository(Community::class, 'password', [
            'name' => $roomName,
        ]);
    }
}
