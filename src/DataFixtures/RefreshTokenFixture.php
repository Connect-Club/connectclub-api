<?php

namespace App\DataFixtures;

use App\Entity\OAuth\RefreshToken;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RefreshTokenFixture extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            UserFixture::class,
            OAuthClientFixture::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $token = new RefreshToken();
        $token->setClient($this->getReference('test-client'));
        $token->setExpiresAt(time() + 3600 * 24);
        $token->setToken(BaseCest::REFRESH_TOKEN);
        $token->setUser($this->getReference('user-test'));

        $manager->persist($token);
        $manager->flush();
    }
}
