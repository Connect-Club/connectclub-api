<?php

namespace App\Tests\VideoRoom;

use App\Controller\ErrorCode;
use App\Entity\Community\Community;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomEvent;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\VideoRoomTokenFixture;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class VideoRoomTokenVersion2Cest extends BaseCest
{
    protected string $tokenUrl = '/v2/video-room/token/';
    const MAIN_MEETING_SID = '5ea687610dc2b';
    const SUCCESS_TOKEN_JSON_FORMAT = [
        'jitsiServer' => 'string',
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
            'withSpeakers' => 'boolean',
            'backgroundObjects' => 'array',
            'backgroundObjectsData' => 'array',
            'isSystemBackground' => 'boolean',
        ],
        'name' => 'string',
        'description' => 'string|null',
        'id' => 'integer',
        'sid' => 'string|null',
        'ownerId' => 'integer',
        'open' => 'boolean',
        'isAdmin' => 'boolean',
        'isDone' => 'boolean',
        'isPrivate' => 'boolean',
        'draftType' => 'string',
        'isSpecialSpeaker' => 'boolean',
        'club' => 'array|null',
        'eventScheduleId' => 'string|null',
        'language' => 'array|null',
    ];

    public function successJoinToVideoRoom(ApiTester $I)
    {
        //Add 49 online users and 51 offline users
        $I->loadFixtures(new VideoRoomTokenFixture(self::MAIN_MEETING_SID, 59, 51), false);

        /** @var VideoRoom $room */
        $room = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_BOB_NAME
            ]
        ]);
        $password = $room->community->password;

        $object = $I->grabEntityFromRepository(VideoRoomPortalObject::class, [
            'background' => [
                'id' => $room->config->backgroundRoom->id,
            ],
        ]);

        $objectMainSpawn = $I->grabEntityFromRepository(VideoRoomMainSpawnObject::class, [
            'background' => [
                'id' => $room->config->backgroundRoom->id,
            ],
        ]);

        $jsonFormat = self::SUCCESS_TOKEN_JSON_FORMAT;
        $jsonFormat['config']['objects'] = [
            $object->id => [
                'type' => 'string',
                'x' => 'integer',
                'y' => 'integer',
                'width' => 'integer',
                'height' => 'integer'
            ],
            $objectMainSpawn->id => [
                'type' => 'string',
                'x' => 'integer',
                'y' => 'integer',
                'width' => 'integer',
                'height' => 'integer'
            ]
        ];
        $jsonFormat['config']['objectsData'] = [
            $object->id => [
                'name' => 'string|null',
                'password' => 'string',
            ],
            $objectMainSpawn->id => 'array'
        ];

        //Join to room with 49 online users
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode(['password' => $password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict($jsonFormat, true);
        $I->seeResponseContainsJson([
            'response' => [
                'name' => self::VIDEO_ROOM_BOB_NAME,
                'id' => $room->id,
                'ownerId' => $room->community->owner->id,
                'description' => $room->community->description,
                'jitsiServer' => $_ENV['JITSI_SERVER'],
                'isAdmin' => false,
            ]
        ]);
        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $room = $manager->getRepository(VideoRoom::class)
                                ->findOneByName(BaseCest::VIDEO_ROOM_BOB_NAME);
                foreach ($room->meetings as $meeting) {
                    $meeting->endTime = time();
                    $manager->persist($meeting);
                    $manager->flush();
                }
            }
        }, true);

        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode(['password' => $password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict($jsonFormat, true);
        $I->grabEntitiesFromRepository(VideoMeeting::class, [
            'videoRoom' => [
                'community' => [
                    'name' => self::VIDEO_ROOM_BOB_NAME
                ],
            ],
            'jitsiCounter' => 0,
        ]);
    }

    public function getTokenWithMaxParticipants(ApiTester $I)
    {
        //Add 50 online users and 49 offline users
        $I->loadFixtures(new VideoRoomTokenFixture(self::MAIN_MEETING_SID, 150, 49), false);

        $password = $I->grabFromRepository(Community::class, 'password', [
            'name' => self::VIDEO_ROOM_BOB_NAME
        ]);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $password
        ]));
        $I->seeResponseCodeIs(HttpCode::PRECONDITION_FAILED);
        $I->seeResponseContainsJson([
            'response' => null,
            'errors' => [
                ErrorCode::V1_VIDEO_ROOM_MAX_COUNT_PARTICIPANTS
            ]
        ]);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $videoRoom = $manager->getRepository('App:Community\Community')->findOneBy([
                    'name' => BaseCest::VIDEO_ROOM_BOB_NAME
                ])->videoRoom;

                $videoRoom->recoveryRoom = $manager->getRepository('App:Community\Community')->findOneBy([
                    'name' => BaseCest::VIDEO_ROOM_TEST_NAME
                ])->videoRoom;

                $manager->persist($videoRoom);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $password
        ]));
        $I->seeResponseCodeIs(HttpCode::FOUND);

        /** @var VideoRoom $recoveryVideoRoom */
        $recoveryVideoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => ['name' => self::VIDEO_ROOM_TEST_NAME]
        ]);

        $I->seeResponseContainsJson([
            'response' => [
                'recoveryRoomName' => $recoveryVideoRoom->community->name,
                'recoveryRoomPassword' => $recoveryVideoRoom->community->password,
            ],
            'errors' => [ErrorCode::V1_VIDEO_ROOM_MAX_COUNT_PARTICIPANTS],
        ]);
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

    public function getTokenDoneRoomByOwner(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $roomRepository = $manager->getRepository(VideoRoom::class);
                $room = $roomRepository->findOneByName(BaseCest::VIDEO_ROOM_BOB_NAME);

                $room->doneAt = time();

                $manager->flush();
            }
        });

        $password = $I->grabFromRepository(Community::class, 'password', [
            'name' => self::VIDEO_ROOM_BOB_NAME,
        ]);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);

        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $password
        ]));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'isDone' => false,
            ],
        ]);
    }

    public function getTokenDoneRoomByGuest(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $roomRepository = $manager->getRepository(VideoRoom::class);
                $room = $roomRepository->findOneByName(BaseCest::VIDEO_ROOM_BOB_NAME);

                $room->doneAt = time();

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $password = $I->grabFromRepository(Community::class, 'password', [
            'name' => self::VIDEO_ROOM_BOB_NAME,
        ]);

        $I->sendPOST($this->tokenUrl.self::VIDEO_ROOM_BOB_NAME, json_encode([
            'password' => $password
        ]));

        $I->seeResponseCodeIs(HttpCode::LOCKED);
    }
}
