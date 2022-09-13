<?php

namespace App\OAuth2;

use App\Entity\OAuth\AccessToken;
use App\Entity\OAuth\Client;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use OAuth2\IOAuth2Storage;
use OAuth2\Model\IOAuth2AccessToken;
use OAuth2\Model\IOAuth2Client;
use OAuth2\Model\OAuth2AccessToken;

/**
 * Class OAuth2.
 */
class OAuth2 extends \OAuth2\OAuth2
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository, IOAuth2Storage $storage, $config = [])
    {
        $this->userRepository = $userRepository;

        parent::__construct($storage, $config);
    }

    /**
     * @param Client      $client
     * @param User|null   $data
     * @param string|null $scope
     * @param int|null    $access_token_lifetime
     * @param bool        $issue_refresh_token
     * @param int|null    $refresh_token_lifetime
     *
     * @return array
     */
    public function createAccessToken(
        IOAuth2Client $client,
        $data,
        $scope = null,
        $access_token_lifetime = null,
        $issue_refresh_token = true,
        $refresh_token_lifetime = null
    ) {
        $scopes = $client->scopes;
        if ($scope) {
            $scopes = array_merge($scopes, explode(' ', $scope));
        }

        if ($data) {
            $scopes = array_merge($scopes, $data->roles->map(fn(Role $role) => strtolower($role->role))->toArray());
        }

        $scope = trim(implode(' ', array_unique($scopes)));

        $token = parent::createAccessToken(
            $client,
            $data,
            $scope,
            $access_token_lifetime,
            $issue_refresh_token,
            $refresh_token_lifetime
        );

        if (method_exists($data, 'getCurrentOAuth2Event')) {
            $token['event'] = $data->getCurrentOAuth2Event();
        }

        return $token;
    }

    public function verifyAccessToken($tokenParam, $scope = null)
    {
        $accessToken = parent::verifyAccessToken($tokenParam, $scope);

        if ($accessToken instanceof AccessToken) {
            $user = $accessToken->getUser();
            if ($user instanceof User) {
                $user->lastTimeActivity = time();
                $this->userRepository->save($user);
            }
        }

        return $accessToken;
    }
}
