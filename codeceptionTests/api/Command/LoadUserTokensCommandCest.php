<?php

namespace App\Tests\Command;

use App\Client\GoogleCloudStorageClient;
use App\Client\NftImageClient;
use App\Entity\Ethereum\UserToken;
use App\Entity\User;
use App\Kernel;
use App\Service\MatchingClient;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class LoadUserTokensCommandCest extends BaseCest
{
    public function testBase64Image(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $user = new User();
                $user->name = 'user#1';
                $user->wallet = 'wallet_id_1';
                $user->state = User::STATE_VERIFIED;
                $manager->persist($user);

                $manager->flush();
            }
        }, true);

        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);
        $command = $application->find('LoadUserTokensCommand');
        $commandTester = new CommandTester($command);

        $user1 = $I->grabEntityFromRepository(User::class, ['name' => 'user#1']);

        $mockMatchingClient = $I->mockMatchingClient()->getMock();

        $tokenData = [
            'name' => 'Base 64 token',
            'tokenId' => '0xHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5',
            //phpcs:ignore
            'image' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaW5ZTWluIG1lZXQiIHZpZXdCb3g9IjAgMCAzNTAgMzUwIj48c3R5bGU+LmJhc2UgeyBmaWxsOiBibGFjazsgZm9udC1mYW1pbHk6IHNlcmlmOyBmb250LXNpemU6IDE0cHg7IH08L3N0eWxlPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IndoaXRlIiAvPjx0ZXh0IHg9IjEwIiB5PSIyMCIgY2xhc3M9ImJhc2UiPldpbmRvd3MgMS4wPC90ZXh0Pjx0ZXh0IHg9IjEwIiB5PSI0MCIgY2xhc3M9ImJhc2UiPlZpc3VhbCBTdHVkaW88L3RleHQ+PHRleHQgeD0iMTAiIHk9IjYwIiBjbGFzcz0iYmFzZSI+U3dlYXQ8L3RleHQ+PHRleHQgeD0iMTAiIHk9IjgwIiBjbGFzcz0iYmFzZSI+R288L3RleHQ+PHRleHQgeD0iMTAiIHk9IjEwMCIgY2xhc3M9ImJhc2UiPk5vbnByb2ZpdDwvdGV4dD48dGV4dCB4PSIxMCIgeT0iMTIwIiBjbGFzcz0iYmFzZSI+UGFyaXM8L3RleHQ+PHRleHQgeD0iMTAiIHk9IjE0MCIgY2xhc3M9ImJhc2UiPkNyaXRpY2FsPC90ZXh0Pjx0ZXh0IHg9IjEwIiB5PSIxNjAiIGNsYXNzPSJiYXNlIj5IeXBlcjwvdGV4dD48L3N2Zz4=',
            'description' => 'Description',
        ];
        $I->mockMatchingClient($mockMatchingClient)->findTokensForUser(1, $user1, [
            'data' => [
                $tokenData
            ],
        ]);
        $I->mockService(MatchingClient::class, $mockMatchingClient);

        $googleCloudMock = Mockery::mock(GoogleCloudStorageClient::class);
        $googleCloudMock->shouldReceive('uploadFile')->times(1)
            ->andReturn(['object' => '0xHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5.gif']);
        $I->mockService(GoogleCloudStorageClient::class, $googleCloudMock);

        $commandTester->execute([]);

        /** @var UserToken $actualUserToken1 */
        //phpcs:ignore
        $actualUserToken1 = $I->grabEntityFromRepository(UserToken::class, ['tokenId' => '0xHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5']);
        $I->assertSame('user#1', $actualUserToken1->user->name);
        $I->assertSame($tokenData['name'], $actualUserToken1->name);
        $I->assertSame($tokenData['tokenId'] . '.gif', $actualUserToken1->nftImage->originalName);
        $I->assertSame($tokenData['tokenId'] . '.gif', $actualUserToken1->nftImage->processedName);
        $I->assertSame($tokenData['description'], $actualUserToken1->description);
    }

    public function testExecute(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $user = new User();
                $user->name = 'user#1';
                $user->wallet = 'wallet_id_1';
                $user->state = User::STATE_VERIFIED;
                $manager->persist($user);

                $user = new User();
                $user->name = 'user#2';
                $user->wallet = 'wallet_id_2';
                $user->state = User::STATE_VERIFIED;
                $manager->persist($user);

                $user = new User();
                $user->name = 'user#3';
                $user->wallet = null;
                $user->state = User::STATE_VERIFIED;
                $manager->persist($user);

                $manager->flush();
            }
        }, true);

        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);
        $command = $application->find('LoadUserTokensCommand');
        $commandTester = new CommandTester($command);

        $user1 = $I->grabEntityFromRepository(User::class, ['name' => 'user#1']);
        $user2 = $I->grabEntityFromRepository(User::class, ['name' => 'user#2']);

        $mockMatchingClient = $I->mockMatchingClient()->getMock();

        $dataUser1 = $I->mockMatchingClient($mockMatchingClient)->createFakeData();
        $I->mockMatchingClient($mockMatchingClient)->findTokensForUser(1, $user1, [
            'data' => $dataUser1,
        ]);
        $dataUser2 = $I->mockMatchingClient($mockMatchingClient)->createFakeData();
        $I->mockMatchingClient($mockMatchingClient)->findTokensForUser(1, $user2, [
            'data' => $dataUser2,
        ]);
        $I->mockService(MatchingClient::class, $mockMatchingClient);

        $nftImageMock = new MockHandler([
            new Response(200, ['Content-Type' => ['image/png']], 'image_1'),
            new Response(200, ['Content-Type' => ['image/gif']], 'image_2'),
            new Response(200, ['Content-Type' => ['image/jpg']], 'image_3'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);
        $handlerStack = HandlerStack::create($nftImageMock);
        $client = new Client(['handler' => $handlerStack]);
        $I->mockService(NftImageClient::class, new NftImageClient($client));

        $googleCloudMock = Mockery::mock(GoogleCloudStorageClient::class);
        $googleCloudMock->shouldReceive('uploadFile')->times(1)
            ->andReturn(['object' => $dataUser1[0]['tokenId'] . '.png']);
        $googleCloudMock->shouldReceive('uploadFile')->times(1)
            ->andReturn(['object' => $dataUser1[1]['tokenId'] . '.gif']);
        $googleCloudMock->shouldReceive('uploadFile')->times(1)
            ->andReturn(['object' => $dataUser2[0]['tokenId'] . '.jpg']);
        $googleCloudMock->shouldReceive('uploadFile')->never()
            ->andReturn(['object' => $dataUser2[1]['tokenId'] . '.png']);
        $I->mockService(GoogleCloudStorageClient::class, $googleCloudMock);

        $commandTester->execute([]);

        /** @var UserToken $actualUserToken1 */
        $actualUserToken1 = $I->grabEntityFromRepository(UserToken::class, ['tokenId' => $dataUser1[0]['tokenId']]);
        $I->assertSame('user#1', $actualUserToken1->user->name);
        $I->assertSame($dataUser1[0]['name'], $actualUserToken1->name);
        $I->assertSame($dataUser1[0]['tokenId'] . '.png', $actualUserToken1->nftImage->originalName);
        $I->assertSame($dataUser1[0]['tokenId'] . '.png', $actualUserToken1->nftImage->processedName);
        $I->assertSame($dataUser1[0]['description'], $actualUserToken1->description);

        /** @var UserToken $actualUserToken2 */
        $actualUserToken2 = $I->grabEntityFromRepository(UserToken::class, ['tokenId' => $dataUser1[1]['tokenId']]);
        $I->assertSame('user#1', $actualUserToken2->user->name);
        $I->assertSame($dataUser1[1]['name'], $actualUserToken2->name);
        $I->assertSame($dataUser1[1]['tokenId'] . '.gif', $actualUserToken2->nftImage->originalName);
        $I->assertSame($dataUser1[1]['tokenId'] . '.gif', $actualUserToken2->nftImage->processedName);
        $I->assertSame($dataUser1[1]['description'], $actualUserToken2->description);

        /** @var UserToken $actualUserToken3 */
        $actualUserToken3 = $I->grabEntityFromRepository(UserToken::class, ['tokenId' => $dataUser2[0]['tokenId']]);
        $I->assertSame('user#2', $actualUserToken3->user->name);
        $I->assertSame($dataUser2[0]['name'], $actualUserToken3->name);
        $I->assertSame($dataUser2[0]['tokenId'] . '.jpg', $actualUserToken3->nftImage->originalName);
        $I->assertSame($dataUser2[0]['tokenId'] . '.jpg', $actualUserToken3->nftImage->processedName);
        $I->assertSame($dataUser2[0]['description'], $actualUserToken3->description);

        $I->dontSeeInRepository(UserToken::class, ['tokenId' => $dataUser2[1]['tokenId']]);
    }
}
