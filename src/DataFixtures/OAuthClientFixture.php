<?php

namespace App\DataFixtures;

use App\Entity\OAuth\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use OAuth2\OAuth2;

/**
 * Class OAuthClientFixture.
 */
class OAuthClientFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['highload'];
    }

    public function load(ObjectManager $manager)
    {
        $client = new Client();
        $client->id = 3;
        $client->setRedirectUris([]);
        $client->setAllowedGrantTypes([
            OAuth2::GRANT_TYPE_IMPLICIT,
            OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS,
            OAuth2::GRANT_TYPE_USER_CREDENTIALS,
            OAuth2::GRANT_TYPE_REFRESH_TOKEN,
            'https://connect.club/sms',
            'https://connect.club/metamask',
        ]);
        $client->setRandomId('3u3bpqxw736s4kgo0gsco4kw48gos800gscg4s4w8w80oogc8c');
        $client->setSecret('6cja0geitwsok4gckw0cc0c04sc0sgwgo8kggcoc08wocsw8wg');

        $manager->persist($client);
        $manager->flush();

        $this->setReference('test-client', $client);

        $unityServerClient = new Client();
        $unityServerClient->setRedirectUris([]);
        $unityServerClient->setAllowedGrantTypes([OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS,]);
        $unityServerClient->setRandomId('1iewkn9zqhcwydogjfr2g933fjxxqk5rnqnk71q7ykxsj1twk8');
        $unityServerClient->setSecret('0vfeoij97x453ck00sxqvr0m6pu5r1lzoh59z930cpkivm84ql');
        $unityServerClient->scopes = ['unity_server'];

        $manager->persist($unityServerClient);
        $manager->flush();

        $this->setReference('test-unity-client', $unityServerClient);
    }
}
