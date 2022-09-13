<?php

namespace App\Tests\Ethereum;

use App\Entity\Ethereum\UserToken;
use App\Entity\Photo\NftImage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SmartContractCest extends BaseCest
{
    public function testToken(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $userToken1 = new UserToken();
                $userToken1->tokenId = '0x1234567890123456789012345678901234567890';
                $userToken1->user = $alice;
                $userToken1->name = 'Alice token 1';
                $userToken1->description = 'Alice token 1 description';
                $userToken1->nftImage = new NftImage(
                    'bucket',
                    'folder/image1.png',
                    'folder/image1.png',
                    $alice
                );
                $manager->persist($userToken1);

                $userToken2 = new UserToken();
                $userToken2->tokenId = '0x1234567890123456789012345678901234567891';
                $userToken2->user = $alice;
                $userToken2->name = 'Alice token 2';
                $userToken2->description = 'Alice token 2 description';
                $userToken2->nftImage = new NftImage(
                    'bucket',
                    'folder/image2.png',
                    'folder/image2.png',
                    $alice
                );
                $manager->persist($userToken2);

                $userToken3 = new UserToken();
                $userToken3->tokenId = '0x1234567890123456789012345678901234567892';
                $userToken3->user = $alice;
                $userToken3->name = 'Alice token 3';
                $userToken3->description = 'Alice token 3 description';
                $userToken3->nftImage = new NftImage(
                    'bucket',
                    'folder/image3.png',
                    'folder/image3.png',
                    $alice
                );
                $manager->persist($userToken3);

                $userToken4 = new UserToken();
                $userToken4->tokenId = '0x1234567890123456789012345678901234567893';
                $userToken4->user = $bob;
                $userToken4->name = 'Bob token 1';
                $userToken4->description = 'Bob token 1 description';
                $userToken4->nftImage = new NftImage(
                    'bucket',
                    'folder/image4.png',
                    'folder/image4.png',
                    $bob
                );
                $manager->persist($userToken4);

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        // get all Alice's tokens
        $I->sendGet('/v1/smart-contract/token?limit=15');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            [
                'tokenId' => '0x1234567890123456789012345678901234567890',
                'title' => 'Alice token 1',
                'description' => 'Alice token 1 description',
                'preview' => "https://pics.connect.lol/:WIDTHx:HEIGHT/folder/image1.png"
            ],
            [
                'tokenId' => '0x1234567890123456789012345678901234567891',
                'title' => 'Alice token 2',
                'description' => 'Alice token 2 description',
                'preview' => "https://pics.connect.lol/:WIDTHx:HEIGHT/folder/image2.png"
            ],
            [
                'tokenId' => '0x1234567890123456789012345678901234567892',
                'title' => 'Alice token 3',
                'description' => 'Alice token 3 description',
                'preview' => "https://pics.connect.lol/:WIDTHx:HEIGHT/folder/image3.png"
            ],
        ]);

        // get Alice's tokens with pagination
        $I->sendGet('/v1/smart-contract/token?limit=2&lastValue=1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items[0]'));
        $I->seeResponseContainsJson([
            [
                'tokenId' => '0x1234567890123456789012345678901234567892',
                'title' => 'Alice token 3',
                'description' => 'Alice token 3 description',
                'preview' => "https://pics.connect.lol/:WIDTHx:HEIGHT/folder/image3.png"
            ],
        ]);
    }
}
