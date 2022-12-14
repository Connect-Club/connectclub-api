<?php

namespace App\Tests\Land;

use App\Entity\Photo\Image;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LandCest extends BaseCest
{
    /** @noinspection PhpSignatureMismatchDuringInheritanceInspection */
    //phpcs:ignore
    public function _before(ApiTester $I)
    {
        parent::_before(); // TODO: Change the autogenerated stub

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $room = $manager->getRepository('App:VideoChat\VideoRoom')
                    ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);
                $room->alwaysReopen = true;

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->wallet = '0x825B176819d99B0c3b128b7561E68108790694Fa';

                $manager->persist(new Image('test', 'main-image.png', 'main-image.png', $main));
                $manager->persist(new Image('test', 'thumb-image.png', 'thumb-image.png', $main));

                $manager->persist($room);
                $manager->flush();
            }
        });
    }

    public function acceptanceTest(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v2/video-room/always-reopen');
        $room = $I->grabDataFromResponse('items[0]');
        $I->assertEquals(self::VIDEO_ROOM_TEST_NAME, $room['name']);

        $mainId = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL])->id;

        $I->sendPost('/v1/land', json_encode([
            'name' => 'Land #0',
            'roomId' => $room['name'],
            'ownerId' => $mainId,
            'description' => 'Hi bb',
            'x' => 0,
            'y' => 10,
            'sector' => 0,
            'available' => true,
        ]));
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson([
            'response' => [
                'thumb' => null,
                'image' => null,
                'sector' => 0,
                'x' => 0,
                'y' => 10,
                'ownerAddress' => '0x825B176819d99B0c3b128b7561E68108790694Fa',
                'ownerUsername' => null,
                'available' => true,
            ]
        ]);

        $mainImageId = $I->grabEntityFromRepository(Image::class, ['originalName' => 'main-image.png'])->id;
        $thumbImageId = $I->grabEntityFromRepository(Image::class, ['originalName' => 'thumb-image.png'])->id;

        $id = $I->grabDataFromResponse('id');
        $I->assertEquals(true, $I->grabDataFromResponse('available'));

        $I->sendPatch('/v1/land/'.$id, json_encode([
            'name' => 'Land #2',
            'roomId' => $room['name'],
            'description' => 'Hi bb 2',
            'x' => 10,
            'y' => 15,
            'sector' => 15,
            'ownerId' => $mainId,
            'available' => false,
            'imageId' => $mainImageId,
            'thumbId' => $thumbImageId,
        ]));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'response' => [
                'thumb' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/thumb-image.png',
                'image' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/main-image.png',
                'sector' => 15,
                'x' => 10,
                'y' => 15,
                'ownerAddress' => '0x825B176819d99B0c3b128b7561E68108790694Fa',
                'ownerUsername' => null,
                'available' => false,
            ]
        ]);

        $I->sendPatch('/v1/land/'.$id, json_encode([
            'name' => 'Land #2',
            'roomId' => $room['name'],
            'description' => 'Hi bb 2',
            'x' => 10,
            'y' => 15,
            'sector' => 15,
            'available' => false,
            'imageId' => null,
            'thumbId' => null,
            'ownerId' => null,
        ]));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'response' => [
                'thumb' => null,
                'image' => null,
                'sector' => 15,
                'x' => 10,
                'y' => 15,
                'ownerAddress' => null,
                'ownerUsername' => null,
                'available' => false,
            ]
        ]);
    }
}
