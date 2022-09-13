<?php

namespace App\DataFixtures;

use App\Entity\OAuth\AccessToken;
use App\Entity\User;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AccessTokenFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['highload'];
    }

    public function getDependencies()
    {
        return [
            UserFixture::class,
            OAuthClientFixture::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $manager->persist($this->createToken(BaseCest::MAIN_ACCESS_TOKEN, $this->getReference('user-test')));
        $manager->persist($this->createToken(BaseCest::LOGOUT_ACCESS_TOKEN, $this->getReference('user-test')));
        $manager->persist($this->createToken(BaseCest::ALICE_ACCESS_TOKEN, $this->getReference('alice-test')));
        $manager->persist($this->createToken(BaseCest::BOB_ACCESS_TOKEN, $this->getReference('bob-test')));
        $manager->persist($this->createToken(BaseCest::MIKE_ACCESS_TOKEN, $this->getReference('mike-test')));

        $unityServerAccessToken = new AccessToken();
        $unityServerAccessToken->setClient($this->getReference('test-unity-client'));
        $unityServerAccessToken->setExpiresAt(time() + 360000);
        $unityServerAccessToken->setToken(BaseCest::UNITY_SERVER_ACCESS_TOKEN);
        $unityServerAccessToken->setScope(implode(' ', $this->getReference('test-unity-client')->scopes));

        $manager->persist($unityServerAccessToken);

        $manager->flush();
    }

    private function createToken($name, User $user)
    {
        $token = new AccessToken();
        $token->setClient($this->getReference('test-client'));
        $token->setExpiresAt(time() + 360000);
        $token->setToken($name);
        $token->setUser($user);

        return $token;
    }
}
