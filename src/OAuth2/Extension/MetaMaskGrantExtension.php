<?php

namespace App\OAuth2\Extension;

use App\Entity\User;
use App\Event\PostRegistrationUserEvent;
use App\Event\PreRegistrationUserEvent;
use App\Exception\User\MetamaskInvalidWalletDataException;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\UserRepository;
use App\Service\MatchingClient;
use App\Service\MetamaskManager;
use FOS\OAuthServerBundle\Storage\GrantExtensionInterface;
use OAuth2\Model\IOAuth2Client;
use OAuth2\OAuth2ServerException;
use Redis;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MetaMaskGrantExtension implements GrantExtensionInterface
{
    private UserRepository $userRepository;
    private EventDispatcherInterface $eventDispatcher;
    private RequestStack $requestStack;
    private MessageBusInterface $bus;
    private MetamaskManager $metamaskManager;
    private Redis $redis;
    private MatchingClient $matchingClient;

    public function __construct(
        UserRepository $userRepository,
        EventDispatcherInterface $eventDispatcher,
        RequestStack $requestStack,
        MessageBusInterface $bus,
        MetamaskManager $metamaskManager,
        Redis $redis,
        MatchingClient $matchingClient
    ) {
        $this->userRepository = $userRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->bus = $bus;
        $this->metamaskManager = $metamaskManager;
        $this->redis = $redis;
        $this->matchingClient = $matchingClient;
    }

    public function checkGrantExtension(IOAuth2Client $client, array $inputData, array $authHeaders): array
    {
        if (empty($inputData['device_id'])) {
            throw new OAuth2ServerException('400', 'oauth2.extension.metamask.device', 'Device not found');
        }

        if (empty($inputData['text'])) {
            throw new OAuth2ServerException('400', 'oauth2.extension.metamask.text', 'Text not found');
        }

        if (empty($inputData['signature'])) {
            throw new OAuth2ServerException('400', 'oauth2.extension.metamask.signature', 'Signature not found');
        }

        if (empty($inputData['address'])) {
            throw new OAuth2ServerException('400', 'oauth2.extension.metamask.address', 'Address not found');
        }

        $metaMaskAuthorizationKey = $inputData['device_id'];
        $redisKey = 'metamask_nonce_for_'.$metaMaskAuthorizationKey;
        $nonce = $this->redis->get($redisKey);

        if (!$nonce) {
            throw new OAuth2ServerException(
                '400',
                'oauth2.extension.metamask.signature',
                'Signature must be generated'
            );
        }

        try {
            $check = $this->metamaskManager->checkMetamaskWallet(
                $nonce,
                $inputData['text'],
                $inputData['address'],
                $inputData['signature']
            );

            if (!$check) {
                throw new OAuth2ServerException(
                    '400',
                    'oauth2.extension.metamask.validation_error',
                    'Metamask validation error'
                );
            }
        } catch (MetamaskInvalidWalletDataException $exception) {
            throw new OAuth2ServerException(
                '400',
                'oauth2.extension.metamask.'.$exception->getMessage(),
                'Metamask validation error'
            );
        }

        $walletAddress = mb_strtolower($inputData['address']);

        $user = $this->userRepository->findOneBy(['wallet' => $walletAddress]);
        if ($user) {
            return ['data' => $user];
        }

        $user = new User();
        $user->state = User::STATE_NOT_INVITED;
        $user->wallet = $walletAddress;

        $this->eventDispatcher->dispatch(new PreRegistrationUserEvent($user));
        $this->userRepository->save($user);

        $deviceId = null;
        if ($request = $this->requestStack->getCurrentRequest()) {
            $deviceId = $request->headers->get('amplDeviceId');
        }

        $message = new AmplitudeEventStatisticsMessage('api.change_state', [], $user, $deviceId);
        $message->userOptions['state'] = $user->state;
        $this->bus->dispatch($message);

        $this->eventDispatcher->dispatch(new PostRegistrationUserEvent($user));

        $amplitudeMessage = new AmplitudeEventStatisticsMessage('api.user_registered', [], $user, $deviceId);
        $amplitudeMessage->userOptions['utm_campaign'] = $user->utmCompaign ?? $user->source;
        $amplitudeMessage->userOptions['utm_source'] = $user->utmSource;
        $amplitudeMessage->userOptions['utm_content'] = $user->utmContent;
        $amplitudeMessage->userOptions['registered_by_wallet'] = true;

        if ($user->registeredByClubLink) {
            $amplitudeMessage->userOptions['clubSlug'] = $user->registeredByClubLink->slug;
        }

        $this->bus->dispatch($amplitudeMessage);

        $this->userRepository->save($user);

        $this->matchingClient->publishEvent('userWalletAdded', $user, ['wallet' => $walletAddress]);

        return ['data' => $user];
    }
}
