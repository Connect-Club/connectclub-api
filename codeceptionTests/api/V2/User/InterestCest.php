<?php

namespace App\Tests\V2\User;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Entity\Interest\Interest;
use App\Entity\User;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\SyncWithIntercomMessage;
use App\Message\UploadUserToElasticsearchMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class InterestCest extends BaseCest
{
    public function testList(ApiTester $I)
    {
        $this->mockElasticsearchClient($I);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $mainUser = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $mainUser->clearInterests();

                $manager->persist($mainUser);
                $manager->flush();
            }
        }, true);

        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEmpty($main->interests->toArray());

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $interests = $I->grabEntitiesFromRepository(Interest::class, ['isOld' => false]);
        $interestsIds = array_map(fn(Interest $i) => ['id' => $i->id, 'name' => $i->name], $interests);

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->sendPatch('/v2/account', json_encode(['interests' => $interestsIds]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->refreshEntities($main);
        $I->assertNotEmpty($main->interests->toArray());
        $I->assertCount(count($interests), $main->interests->toArray());

        foreach ($interests as $interest) {
            $found = false;
            foreach ($main->interests as $userInterest) {
                if ($interest->id == $userInterest->id) {
                    $found = true;
                    break;
                }
            }
            $I->assertTrue($found, 'Not found user interest '.$interest->name);
        }
    }

    private function mockElasticsearchClient(ApiTester $I, bool $userRegisteredAmplitudeStatistics = false)
    {
        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::type(UploadUserToElasticsearchMessage::class))
            ->andReturn(new Envelope(Mockery::mock(UploadUserToElasticsearchMessage::class)));
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::type(SyncWithIntercomMessage::class))
            ->andReturn(new Envelope(Mockery::mock(SyncWithIntercomMessage::class)));

        if ($userRegisteredAmplitudeStatistics) {
            $busMock
                ->shouldReceive('dispatch')
                ->with(Mockery::on(fn($message) => $message instanceof AmplitudeEventStatisticsMessage))
                ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        }

        $I->mockService(MessageBusInterface::class, $busMock);
    }
}
