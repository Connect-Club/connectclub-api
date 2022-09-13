<?php

namespace App\Tests\VideoRoom;

use App\Entity\Ethereum\UserToken;
use App\Entity\Photo\NftImage;
use App\Entity\User;
use App\Entity\VideoChat\Object\ShareScreenObject;
use App\Entity\VideoChat\Object\VideoRoomImageZoneObject;
use App\Entity\VideoChat\Object\VideoRoomNftImageObject;
use App\Entity\VideoChat\Object\VideoRoomObjectTimeBox;
use App\Entity\VideoChatObject\QuietZoneObject;
use Mockery;
use App\Client\GoogleCloudStorageClient;
use App\Controller\ErrorCode;
use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\Entity\Photo\VideoRoomImageObjectPhoto;
use App\Entity\Role;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use App\Entity\VideoChat\Object\VideoRoomImageObject;
use App\Entity\VideoChat\Object\VideoRoomMainSpawnObject;
use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use App\Entity\VideoChat\Object\VideoRoomSquarePortalObject;
use App\Entity\VideoChat\Object\VideoRoomStaticObject;
use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VideoRoomObjectsCest extends BaseCest
{
    const VIDEO_MEETING_SID = '5f1e7d761b528';
    const DEFAULT_JSON_OBJECT_TYPE = [
        'type' => 'string',
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function getObjectsTest(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')
                    ->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $testCommunity = $manager->getRepository('App:Community\Community')
                    ->findOneBy(['name' => BaseCest::VIDEO_ROOM_TEST_NAME]);

                $testVideoRoom = $testCommunity->videoRoom;

                $testVideoRoom->objects->add(new VideoRoomPortalObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300,
                    'portal',
                    'qwerty'
                ));

                $testVideoRoom->objects->add(new VideoRoomMainSpawnObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300
                ));

                $testVideoRoom->objects->add(new VideoRoomFireplaceObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300,
                    40,
                    'lottie',
                    'sound'
                ));

                $testVideoRoom->objects->add(new VideoRoomVideoObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300,
                    40,
                    'video_src',
                    20
                ));

                $testVideoRoom->objects->add(new VideoRoomSpeakerLocationObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300
                ));

                $testVideoRoom->objects->add(new VideoRoomSquarePortalObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300,
                    'square_portal_object'
                ));

                $testVideoRoom->objects->add(new VideoRoomObjectTimeBox(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300
                ));

                $testVideoRoom->objects->add(new VideoRoomStaticObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300,
                ));

                $testVideoRoom->objects->add(new VideoRoomImageZoneObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300,
                ));

                $objectImagePhoto = new VideoRoomImageObjectPhoto('default', 'original.png', 'processed.png', $main);
                $manager->persist($objectImagePhoto);

                $objectImage = new VideoRoomImageObject(
                    $testVideoRoom,
                    null,
                    new Location(),
                    300,
                    300
                );
                $objectImage->title = 'Title';
                $objectImage->description = 'Desc';
                $objectImage->photo = $objectImagePhoto;
                $testVideoRoom->objects->add($objectImage);

                $testVideoRoom->meetings->add(
                    new VideoMeeting($testVideoRoom, VideoRoomObjectsCest::VIDEO_MEETING_SID, time())
                );

                foreach ($testVideoRoom->config->backgroundRoom->objects as $object) {
                    $manager->remove($object);
                }

                $manager->persist($testVideoRoom);
                $manager->flush();
            }
        }, true);

        $testVideoRoomId = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME
            ]
        ])->id;

        $mainSpawnObjectId = $I->grabEntityFromRepository(VideoRoomMainSpawnObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $speakerLocationObjectId = $I->grabEntityFromRepository(VideoRoomSpeakerLocationObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $portalObjectId = $I->grabEntityFromRepository(VideoRoomPortalObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $fireplaceObjectId = $I->grabEntityFromRepository(VideoRoomFireplaceObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $videoObjectId = $I->grabEntityFromRepository(VideoRoomVideoObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $squarePortalObjectId = $I->grabEntityFromRepository(VideoRoomSquarePortalObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $staticObjectId = $I->grabEntityFromRepository(VideoRoomStaticObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $imageZoneObjectId = $I->grabEntityFromRepository(VideoRoomImageZoneObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $imageObjectId = $I->grabEntityFromRepository(VideoRoomImageObject::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;
        $timeBoxObjectId = $I->grabEntityFromRepository(VideoRoomObjectTimeBox::class, [
            'videoRoom' => [
                'id' => $testVideoRoomId
            ]
        ])->id;

        $I->sendGET('/v1/video-room/sid/'.VideoRoomObjectsCest::VIDEO_MEETING_SID);
        $I->seeResponseMatchesJsonType([
            'objects' => [
                $mainSpawnObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $speakerLocationObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $portalObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $fireplaceObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $videoObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $squarePortalObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $staticObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $imageObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $imageZoneObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
                $timeBoxObjectId => self::DEFAULT_JSON_OBJECT_TYPE,
            ]
        ], '$.response.config');

        //Check types work correct
        $I->assertEquals(
            VideoRoomObject::TYPE_MAIN_SPAWN,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$mainSpawnObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_SPEAKER_LOCATION,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$speakerLocationObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_PORTAL,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$portalObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_FIREPLACE,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$fireplaceObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_VIDEO,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$videoObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_SQUARE_PORTAL,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$squarePortalObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_STATIC_OBJECT,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$staticObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_IMAGE_ZONE,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$imageZoneObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_IMAGE,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$imageObjectId]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_TIME_BOX,
            $I->grabDataFromResponseByJsonPath('$.response.config.objects')[0][$timeBoxObjectId]['type']
        );

        //Check object data
        $I->assertEquals(
            'portal',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$portalObjectId]['name']
        );
        $I->assertEquals(
            'qwerty',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$portalObjectId]['password']
        );

        $I->assertEquals(
            'Title',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$imageObjectId]['title']
        );
        $I->assertEquals(
            'Desc',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$imageObjectId]['description']
        );

        //Fireplace object data
        $I->assertEquals(
            40,
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$fireplaceObjectId]['radius']
        );
        $I->assertEquals(
            'lottie',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$fireplaceObjectId]['lottieSrc']
        );
        $I->assertEquals(
            'sound',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$fireplaceObjectId]['soundSrc']
        );

        //Video object data
        $I->assertEquals(
            40,
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$videoObjectId]['radius']
        );
        $I->assertEquals(
            'video_src',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$videoObjectId]['videoSrc']
        );
        $I->assertEquals(
            20,
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$videoObjectId]['length']
        );

        //Square object data
        $I->assertEquals(
            'square_portal_object',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$squarePortalObjectId]['name']
        );

        $I->assertEquals(
            'https://storage.googleapis.com/default/original.png',
            $I->grabDataFromResponseByJsonPath('$.response.config.objectsData')[0][$imageObjectId]['src']
        );
    }

    public function acceptanceTestPatchObjectsAndLaterListThem(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->loadFixtures(new class extends AbstractFixture implements DependentFixtureInterface {
            public function getDependencies()
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $mainUser = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $background = new BackgroundPhoto('bucket_patch_object_test', '', '', 200, 200, $mainUser);
                $manager->persist($background);
                $manager->flush();
            }
        }, false);
        $background = $I->grabEntityFromRepository(BackgroundPhoto::class, ['bucket' => 'bucket_patch_object_test']);

        $googleCloudMock = Mockery::mock(GoogleCloudStorageClient::class);
        $googleCloudMock->shouldReceive('uploadImage')->andReturn(['object' => 'processed.png']);
        $I->mockService(GoogleCloudStorageClient::class, $googleCloudMock);

        $I->sendPOST('/v1/video-room-object/upload/image', [], [
            'photo' => [
                'name' => 'video_room_background.png',
                'type' => 'image/png',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(codecept_data_dir('video_room_background.png')),
                'tmp_name' => codecept_data_dir('video_room_background.png')
            ]
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $objectId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];
        $objectType = $I->grabDataFromResponseByJsonPath('$.response.type')[0];

        $requestBody = json_encode([
            [
                'type' => 'fireplace',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'radius' => 25.5,
                'lottieSrc' => 'fireplace',
                'soundSrc' => 'fireplace'
            ],
            [
                'type' => 'square_portal',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'name' => 'Square name'
            ],
            [
                'type' => 'main_spawn',
                'location' => [
                    'x' => 500,
                    'y' => 500
                ],
                'width' => 2000,
                'height' => 2000
            ],
            [
                'type' => 'video',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'radius' => 25.5,
                'length' => 100,
                'videoSrc' => 'asdsad'
            ],
            [
                'type' => 'portal',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'name' => 'Portal video room name',
                'password' => 'Portal video room password'
            ],
            [
                'type' => 'speaker_location',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ],
            [
                'type' => 'static_object',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ],
            [
                'type' => 'share_screen',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ],
            [
                'type' => 'image_zone',
                'location' => [
                    'x' => 100,
                    'y' => 100,
                ],
                'width' => 100,
                'height' => 100,
            ],
            [
                'type' => 'quiet_zone',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 10,
                'height' => 10,
                'radius' => 25.5,
            ],
            [
                'type' => $objectType,
                'id' => $objectId,
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ]
        ]);
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPATCH('/v1/video-room-background/'.$background->id.'/objects', $requestBody);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_ACCESS_DENIED]]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPATCH('/v1/video-room-background/'.$background->id.'/objects', $requestBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        $repositoryOptions = ['background' => ['id' => $background->id]];
        $portal = $I->grabEntityFromRepository(VideoRoomPortalObject::class, $repositoryOptions);
        $squarePortal = $I->grabEntityFromRepository(VideoRoomSquarePortalObject::class, $repositoryOptions);
        $mainSpawn = $I->grabEntityFromRepository(VideoRoomMainSpawnObject::class, $repositoryOptions);
        $fireplace = $I->grabEntityFromRepository(VideoRoomFireplaceObject::class, $repositoryOptions);
        $video = $I->grabEntityFromRepository(VideoRoomVideoObject::class, $repositoryOptions);
        $speakerLocation = $I->grabEntityFromRepository(VideoRoomSpeakerLocationObject::class, $repositoryOptions);
        $staticObject = $I->grabEntityFromRepository(VideoRoomStaticObject::class, $repositoryOptions);
        $imageZoneObject = $I->grabEntityFromRepository(VideoRoomImageZoneObject::class, $repositoryOptions);
        $imageObject = $I->grabEntityFromRepository(VideoRoomImageObject::class, $repositoryOptions);
        $shareScreenObject = $I->grabEntityFromRepository(ShareScreenObject::class, $repositoryOptions);
        $quietZoneObject = $I->grabEntityFromRepository(QuietZoneObject::class, $repositoryOptions);

        $I->assertEquals($objectId, $imageObject->id);

        $I->sendGET('/v1/video-room-background');
        $I->seeResponseCodeIs(HttpCode::OK);
        //Check types work correct
        $I->assertEquals(
            VideoRoomObject::TYPE_MAIN_SPAWN,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$mainSpawn->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_SPEAKER_LOCATION,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$speakerLocation->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_PORTAL,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$portal->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_FIREPLACE,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$fireplace->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_VIDEO,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$video->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_SQUARE_PORTAL,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$squarePortal->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_STATIC_OBJECT,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$staticObject->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_IMAGE_ZONE,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$imageZoneObject->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_IMAGE,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$imageObject->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_SHARE_SCREEN,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$shareScreenObject->id]['type']
        );
        $I->assertEquals(
            VideoRoomObject::TYPE_QUIET_ZONE,
            $I->grabDataFromResponseByJsonPath('$.response[0].objects')[0][$quietZoneObject->id]['type']
        );

        //Check object data
        $I->assertEquals(
            'Portal video room name',
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$portal->id]['name']
        );
        $I->assertEquals(
            'Portal video room password',
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$portal->id]['password']
        );

        //Fireplace object data
        $I->assertEquals(
            25.5,
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$fireplace->id]['radius']
        );
        $I->assertEquals(
            'fireplace',
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$fireplace->id]['lottieSrc']
        );
        $I->assertEquals(
            'fireplace',
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$fireplace->id]['soundSrc']
        );

        //Quiet zone data
        $I->assertEquals(
            25.5,
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$quietZoneObject->id]['radius']
        );

        //Video object data
        $I->assertEquals(
            25.5,
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$video->id]['radius']
        );
        $I->assertEquals(
            'asdsad',
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$video->id]['videoSrc']
        );
        $I->assertEquals(
            100,
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$video->id]['length']
        );

        //Square object data
        $I->assertEquals(
            'Square name',
            $I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$squarePortal->id]['name']
        );

        //Image object data
        $I->assertNotNull($I->grabDataFromResponseByJsonPath('$.response[0].objectsData')[0][$imageObject->id]['src']);

        $I->loadFixtures(new class extends AbstractFixture implements DependentFixtureInterface {
            public function getDependencies()
            {
                return [AccessTokenFixture::class, VideoRoomFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $manager->persist(new Role($user, Role::ROLE_ADMIN));
                $manager->flush();
            }
        }, false);
        $I->sendPOST('/v1/video-room-object/upload/image', [], [
            'photo' => [
                'name' => 'video_room_background.png',
                'type' => 'image/png',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(codecept_data_dir('video_room_background.png')),
                'tmp_name' => codecept_data_dir('video_room_background.png')
            ]
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $objectId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];
        $objectType = $I->grabDataFromResponseByJsonPath('$.response.type')[0];

        $requestBody = json_encode([
            [
                'type' => 'fireplace',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'radius' => 25.5,
                'lottieSrc' => 'fireplace',
                'soundSrc' => 'fireplace'
            ],
            [
                'type' => 'square_portal',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'name' => 'Square name'
            ],
            [
                'type' => 'main_spawn',
                'location' => [
                    'x' => 500,
                    'y' => 500
                ],
                'width' => 2000,
                'height' => 2000
            ],
            [
                'type' => 'video',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'radius' => 25.5,
                'length' => 100,
                'videoSrc' => 'asdsad'
            ],
            [
                'type' => 'portal',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100,
                'name' => 'Portal video room name',
                'password' => 'Portal video room password'
            ],
            [
                'type' => 'speaker_location',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ],
            [
                'type' => 'static_object',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ],
            [
                'type' => 'image_zone',
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ],
            [
                'type' => $objectType,
                'id' => $objectId,
                'location' => [
                    'x' => 100,
                    'y' => 100
                ],
                'width' => 100,
                'height' => 100
            ]
        ]);

        $I->sendPATCH('/v1/video-room-object/video-room/'.self::VIDEO_ROOM_TEST_NAME, $requestBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        $repositoryOptions = ['videoRoom' => [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME
            ]
        ]];
        $I->seeInRepository(VideoRoomPortalObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomSquarePortalObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomMainSpawnObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomFireplaceObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomVideoObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomSpeakerLocationObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomStaticObject::class, $repositoryOptions);
        $I->seeInRepository(VideoRoomImageZoneObject::class, $repositoryOptions);
        $imageObject = $I->grabEntityFromRepository(VideoRoomImageObject::class, $repositoryOptions);

        $I->assertEquals($objectId, $imageObject->id);

        $I->sendPATCH('/v1/video-room-object/video-room/'.self::VIDEO_ROOM_TEST_NAME, $requestBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(VideoRoomObject::class, [
            'id' => $objectId,
        ]);
    }

    public function createTokenImageTest(ApiTester $I): void
    {
        $I->wantTo('Create token image');
        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $mainUserToken = new UserToken();
                $mainUserToken->user = $main;
                $mainUserToken->tokenId = '0x1234567890';
                $mainUserToken->name = 'Main user token';
                $mainUserToken->description = 'Main user token description';
                $mainUserToken->nftImage = new NftImage(
                    'bucket',
                    'https://example.com/image1.png',
                    'folder/image1.png',
                    $main
                );
                $manager->persist($mainUserToken);

                // create userToken for Alice
                $aliceUserToken = new UserToken();
                $aliceUserToken->user = $alice;
                $aliceUserToken->tokenId = '0x1234567891';
                $aliceUserToken->name = 'Alice user token';
                $aliceUserToken->description = 'Alice user token description';
                $aliceUserToken->nftImage = new NftImage(
                    'bucket',
                    'https://example.com/image2.png',
                    'folder/image2.png',
                    $alice
                );
                $manager->persist($aliceUserToken);

                $manager->flush();
            }
        });

        /** @var UserToken $mainUserToken */
        $mainUserToken = $I->grabEntityFromRepository(UserToken::class, ['tokenId' => '0x1234567890']);
        /** @var UserToken $aliceUserToken */
        $aliceUserToken = $I->grabEntityFromRepository(UserToken::class, ['tokenId' => '0x1234567891']);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        // get main user token image
        $I->sendPost("/v1/video-room-object/token/{$mainUserToken->tokenId}/image");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => $I->grabDataFromResponseByJsonPath('$.response.id')[0],
            'type' => $I->grabDataFromResponseByJsonPath('$.response.type')[0],
        ]);

        $I->seeInRepository(VideoRoomNftImageObject::class, [
            'id' => $I->grabDataFromResponseByJsonPath('$.response.id')[0],
            'title' => null,
            'description' => null,
        ]);

        // get Alice user token image
        $I->sendPost("/v1/video-room-object/token/{$aliceUserToken->tokenId}/image");
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }
}
