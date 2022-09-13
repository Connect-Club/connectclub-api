<?php

namespace App\Tests\VideoRoom;

use App\Controller\ErrorCode;
use App\Entity\Chat\Chat;
use App\Entity\Chat\ChatParticipant;
use App\Entity\Role;
use App\Entity\User;
use App\Message\UploadUserToElasticsearchMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Interest\InterestCest;
use Codeception\Util\HttpCode;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class UsersCest extends BaseCest
{
    const FORMAT_USER_INFO = [
        'name' => 'string',
        'surname' => 'string',
        'avatarSrc' => 'string|null',
        'company' => 'string|null',
        'position' => 'string|null',
        'deleted' => 'boolean',
        'about' => 'string|null',
        'country' => ['id' => 'integer', 'name' => 'string'],
        'city' => ['id' => 'integer', 'name' => 'string'],
        'interests' => [InterestCest::INTEREST_JSON_FORMAT, InterestCest::INTEREST_JSON_FORMAT],
        'badges' => 'array',
        'shortBio' => 'string|null',
        'longBio' => 'string|null',
        'twitter' => 'string|null',
        'instagram' => 'string|null',
        'linkedin' => 'string|null',
    ];

    public function testDeleteUserAsAdmin(ApiTester $I)
    {
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendDELETE('/v1/user/'.$aliceId);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_ACCESS_DENIED]]);

        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $mainUser = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $role = new Role($mainUser, Role::ROLE_ADMIN);
                $manager->persist($role);
                $manager->flush();
            }
        }, true);

        $I->seeInRepository(Role::class, [
            'user' => [
                'email' => self::BOB_USER_EMAIL
            ],
            'role' => 'admin',
        ]);

        $I->refreshEntities($I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]));

        $I->sendDELETE('/v1/user/0');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_USER_NOT_FOUND]]);

        $I->sendDELETE('/v1/user/'.$aliceId);
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);
        $I->assertNotNull($alice->deleted);
    }

    public function testUpdateSuperCreator(ApiTester $I): void
    {
        $bus = Mockery::mock(MessageBusInterface::class);
        $I->mockService(MessageBusInterface::class, $bus);

        $bus->shouldReceive('dispatch')
            ->times(5)
            ->andReturn(new Envelope(Mockery::mock(UploadUserToElasticsearchMessage::class)));

        /** @var User $alice */
        $alice = $I->grabEntityFromRepository(User::class, [
            'email' => self::ALICE_USER_EMAIL,
        ]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPatch("/v1/user/$alice->id", json_encode([
            'isSuperCreator' => true,
        ]));
        $this->assertUserHasRole($I, $alice, 'supercreator');

        $I->sendPatch("/v1/user/$alice->id", json_encode([
            'isSuperCreator' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPatch("/v1/user/$alice->id", json_encode([
            'isSuperCreator' => false,
        ]));
        $this->assertUserHasNoRole($I, $alice, 'supercreator');

        $I->sendPatch("/v1/user/$alice->id", json_encode([
            'isSuperCreator' => false,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPatch("/v1/user/$alice->id", json_encode([
            'name' => 'Changed',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(User::class, [
            'id' => $alice->id,
            'name' => 'Changed',
        ]);
    }

    private function assertUserHasRole(ApiTester $I, User $user, string $role): void
    {
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(Role::class, [
            'role' => $role,
            'user' => $user,
        ]);
    }

    private function assertUserHasNoRole(ApiTester $I, User $user, string $role): void
    {
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInRepository(Role::class, [
            'role' => $role,
            'user' => $user,
        ]);

        $I->dontSeeInRepository(Role::class, [
            'role' => $role,
            'user' => null,
        ]);
    }
}
