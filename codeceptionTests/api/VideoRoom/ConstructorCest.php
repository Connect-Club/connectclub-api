<?php

namespace App\Tests\VideoRoom;

use App\Entity\Community\Community;
use App\Entity\Photo\NftImage;
use App\Entity\User;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use App\Entity\VideoChat\Object\VideoRoomStaticObject;
use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use App\Entity\VideoChat\VideoRoomObject;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ConstructorCest extends BaseCest
{
    public function testRemoveBackgroundStaticObject(ApiTester $I)
    {
        $this->loadFixturesForVideoRoom($I);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/video-room/6266a9f9aeacd');
        $I->seeResponseCodeIs(HttpCode::OK);

        $videoRoomVideoObject = $this->grabVideoRoomVideoObject($I);
        $backgroundStaticObject = $this->grabBackgroundStaticObject($I);

        $I->seeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $videoRoomVideoObject->id => ['type' => VideoRoomObject::TYPE_VIDEO],
                        $backgroundStaticObject->id => ['type' => VideoRoomObject::TYPE_STATIC_OBJECT],
                    ]
                ]
            ]
        ]);

        $I->sendPatch('/v1/video-room-object/video-room/6266a9f9aeacd', json_encode([
            ['id' => $videoRoomVideoObject->id, 'type' => VideoRoomObject::TYPE_VIDEO]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v1/video-room/6266a9f9aeacd');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $videoRoomVideoObject->id => ['type' => VideoRoomObject::TYPE_VIDEO],
                    ]
                ]
            ]
        ]);
        $I->dontSeeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $backgroundStaticObject->id => ['type' => VideoRoomObject::TYPE_STATIC_OBJECT],
                    ]
                ]
            ]
        ]);

        $this->grabBackgroundStaticObject($I);
    }

    public function testUpdateBackgroundStaticObject(ApiTester $I)
    {
        $this->loadFixturesForVideoRoom($I);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/video-room/6266a9f9aeacd');
        $I->seeResponseCodeIs(HttpCode::OK);

        $videoRoomVideoObject = $this->grabVideoRoomVideoObject($I);
        $backgroundStaticObject = $this->grabBackgroundStaticObject($I);

        $I->assertEquals(30, $backgroundStaticObject->location->x);
        $I->assertEquals(30, $backgroundStaticObject->location->y);

        $I->seeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $videoRoomVideoObject->id => [
                            'type' => VideoRoomObject::TYPE_VIDEO,
                        ],
                        $backgroundStaticObject->id => [
                            'type' => VideoRoomObject::TYPE_STATIC_OBJECT,
                            'x' => 30,
                            'y' => 30,
                        ],
                    ]
                ]
            ]
        ]);

        $I->sendPatch('/v1/video-room-object/video-room/6266a9f9aeacd', json_encode([
            [
                'id' => $videoRoomVideoObject->id,
                'type' => VideoRoomObject::TYPE_VIDEO,
            ],
            [
                'id' => $backgroundStaticObject->id,
                'type' => VideoRoomObject::TYPE_STATIC_OBJECT,
                'location' => [
                    'x' => 3999,
                    'y' => 3999,
                ]
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v1/video-room/6266a9f9aeacd');
        $I->seeResponseCodeIs(HttpCode::OK);

        //Created new object for video room
        $newBackgroundStaticObject = $this->grabVideoRoomStaticObject($I);
        $I->assertNotEquals($newBackgroundStaticObject->id, $backgroundStaticObject->id);

        $I->seeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $videoRoomVideoObject->id => [
                            'type' => VideoRoomObject::TYPE_VIDEO
                        ],
                        $newBackgroundStaticObject->id => [
                            'type' => VideoRoomObject::TYPE_STATIC_OBJECT,
                            'x' => 3999,
                            'y' => 3999,
                        ],
                    ],
                ]
            ]
        ]);

        $I->dontSeeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $backgroundStaticObject->id => [
                            'type' => VideoRoomObject::TYPE_STATIC_OBJECT
                        ],
                    ]
                ]
            ]
        ]);

        $backgroundStaticObjectVer2 = $this->grabBackgroundStaticObject($I);
        $I->assertEquals($backgroundStaticObjectVer2->id, $backgroundStaticObject->id);
        $I->assertEquals(30, $backgroundStaticObjectVer2->location->x);
        $I->assertEquals(30, $backgroundStaticObjectVer2->location->y);
    }

    public function testAddNftImageToVideoRoom(ApiTester $I): void
    {
        $this->loadFixturesForVideoRoom($I);
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $community = $manager->getRepository(Community::class)->findOneBy(['name' => '6266a9f9aeacd']);
                $videoRoom = $community->videoRoom;

                $videoRoomNftImageObject = new VideoRoomNftImageObject(
                    $videoRoom,
                    null,
                    new Location(45, 46),
                    230,
                    100
                );
                $videoRoomNftImageObject->photo = new NftImage(
                    'api-files-api-test-268710',
                    'test.png',
                    'test.png',
                    $main
                );
                $manager->persist($videoRoomNftImageObject->photo);
                $manager->persist($videoRoomNftImageObject);

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/video-room/6266a9f9aeacd');
        $I->seeResponseCodeIs(HttpCode::OK);

        $videoRoomNftImageObject = $this->grabVideoRoomNftImageObject($I);
        $I->seeResponseContainsJson([
            'response' => [
                'config' => [
                    'objects' => [
                        $videoRoomNftImageObject->id => [
                            'type' => VideoRoomObject::TYPE_NFT_IMAGE,
                            'x' => 45,
                            'y' => 46,
                            'width' => 230,
                            'height' => 100,
                        ],
                    ],
                    'objectsData' => [
                        $videoRoomNftImageObject->id => [
                            'src' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/test.png',
                        ],
                    ],
                ]
            ]
        ]);
    }

    private function grabVideoRoomVideoObject(ApiTester $I): VideoRoomVideoObject
    {
        /** @var VideoRoomVideoObject $videoRoomObject */
        $videoRoomObject = $I->grabEntityFromRepository(VideoRoomVideoObject::class, [
            'videoRoom' => [
                'community' => [
                    'name' => '6266a9f9aeacd',
                ]
            ]
        ]);

        return $videoRoomObject;
    }

    private function grabVideoRoomNftImageObject(ApiTester $I): VideoRoomNftImageObject
    {
        return $I->grabEntityFromRepository(VideoRoomNftImageObject::class, [
            'videoRoom' => [
                'community' => [
                    'name' => '6266a9f9aeacd',
                ]
            ]
        ]);
    }

    private function grabVideoRoomStaticObject(ApiTester $I): VideoRoomStaticObject
    {
        /** @var VideoRoomStaticObject $videoRoomObject */
        $videoRoomObject = $I->grabEntityFromRepository(VideoRoomStaticObject::class, [
            'videoRoom' => [
                'community' => [
                    'name' => '6266a9f9aeacd',
                ]
            ]
        ]);

        return $videoRoomObject;
    }

    private function grabBackgroundStaticObject(ApiTester $I): VideoRoomStaticObject
    {
        /** @var VideoRoomStaticObject $videoRoomObject */
        $videoRoomObject = $I->grabEntityFromRepository(VideoRoomStaticObject::class, [
            'background' => [
                'videoRooms' => [
                    'videoRoom' => [
                        'community' => [
                            'name' => '6266a9f9aeacd',
                        ]
                    ]
                ]
            ]
        ]);

        return $videoRoomObject;
    }

    private function loadFixturesForVideoRoom(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $backgroundPhoto = new BackgroundPhoto('bucket', 'cons_original.png', 'processed.png', 100, 200, $main);

                $community = new Community($main, '6266a9f9aeacd');
                $videoRoom = $community->videoRoom;
                $videoRoom->config->backgroundRoom = $backgroundPhoto;

                $manager->persist($backgroundPhoto);
                $manager->persist($videoRoom);

                $manager->persist(new VideoRoomStaticObject(
                    null,
                    $backgroundPhoto,
                    new Location(30, 30),
                    300,
                    300
                ));

                $manager->persist(new VideoRoomVideoObject(
                    $videoRoom,
                    null,
                    new Location(10, 10),
                    100,
                    100,
                    100,
                    'http://youtu.be/griFRQd12DF',
                    3000
                ));

                $manager->flush();
            }
        });
    }
}
