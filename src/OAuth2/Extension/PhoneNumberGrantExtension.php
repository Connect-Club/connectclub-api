<?php

namespace App\OAuth2\Extension;

use App\Entity\User;
use App\Event\PostRegistrationUserEvent;
use App\Event\PreRegistrationUserEvent;
use App\Event\User\UserInvitedEvent;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\Invite\InviteRepository;
use App\Repository\UserRepository;
use App\Service\EventLogManager;
use App\Service\PhoneNumberManager;
use App\Service\SMS\TwoFactorAuthenticatorManager;
use FOS\OAuthServerBundle\Storage\GrantExtensionInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use OAuth2\Model\IOAuth2Client;
use OAuth2\OAuth2ServerException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PhoneNumberGrantExtension implements GrantExtensionInterface
{
    const ERROR_INCORRECT_PHONE_NUMBER = 'oauth2.extension.phone_number.incorrect';
    const ERROR_PHONE_NOT_FOUND = 'oauth2.extension.phone_number.not_found';
    const ERROR_CODE_NOT_FOUND = 'oauth2.extension.code.not_found';
    const ERROR_INCORRECT_CODE = 'oauth2.extension.code.incorrect';
    const ERROR_USER_IS_NOT_VERIFIED = 'oauth2.extension.user.not_verified';

    private TwoFactorAuthenticatorManager $twoFactorAuthenticatorManager;
    private EventLogManager $eventLogManager;
    private InviteRepository $inviteRepository;
    private UserRepository $userRepository;
    private EventDispatcherInterface $eventDispatcher;
    private MessageBusInterface $bus;
    private PhoneNumberManager $phoneNumberManager;
    private RequestStack $requestStack;

    public function __construct(
        TwoFactorAuthenticatorManager $twoFactorAuthenticatorManager,
        EventLogManager $eventLogManager,
        InviteRepository $inviteRepository,
        UserRepository $userRepository,
        EventDispatcherInterface $eventDispatcher,
        MessageBusInterface $bus,
        PhoneNumberManager $phoneNumberManager,
        RequestStack $requestStack
    ) {
        $this->twoFactorAuthenticatorManager = $twoFactorAuthenticatorManager;
        $this->eventLogManager = $eventLogManager;
        $this->inviteRepository = $inviteRepository;
        $this->userRepository = $userRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $bus;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->requestStack = $requestStack;
    }

    public function checkGrantExtension(IOAuth2Client $client, array $inputData, array $authHeaders): array
    {
        if (empty($inputData['phone'])) {
            throw new OAuth2ServerException('400', self::ERROR_PHONE_NOT_FOUND, 'Phone not provided');
        }

        if (empty($inputData['code'])) {
            throw new OAuth2ServerException('400', self::ERROR_CODE_NOT_FOUND, 'Code not provided');
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($inputData['phone']);
        } catch (NumberParseException $numberParseException) {
            throw new OAuth2ServerException('400', self::ERROR_INCORRECT_PHONE_NUMBER, 'Provided phone incorrect');
        }

        $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);

        if (!$this->twoFactorAuthenticatorManager->checkVerificationCode($phoneNumberObject, $inputData['code'])) {
            $this->eventLogManager->logEventCustomObject(
                'sms_verification_incorrect_code',
                'phone_number',
                $phoneNumber,
            );

            throw new OAuth2ServerException('400', self::ERROR_INCORRECT_CODE, 'Provided code incorrect');
        }

        if ($user = $this->userRepository->findOneBy(['phone' => $phoneNumberObject])) {
            $this->eventLogManager->logEvent($user, 'success_authorize_by_sms', ['phone' => $inputData['phone']]);

            return ['data' => $user];
        }

        $userHasBeenInvited = false;
        $user = new User();
        $user->state = User::STATE_NOT_INVITED;

        if ($invite = $this->inviteRepository->findActiveInviteWithPhoneNumber($phoneNumberObject)) {
            $user->state = User::STATE_INVITED;
            $userHasBeenInvited = true;
        }

        $user->phone = $phoneNumberObject;
        $user->isTester = $_ENV['STAGE'] != 1 && $this->phoneNumberManager->isTestPhone(
            PhoneNumberUtil::getInstance()->format($phoneNumberObject, PhoneNumberFormat::E164)
        );

        $this->eventDispatcher->dispatch(new PreRegistrationUserEvent($user));
        $this->userRepository->save($user);

        $deviceId = null;
        if ($request = $this->requestStack->getCurrentRequest()) {
            $deviceId = $request->headers->get('amplDeviceId');
        }

        if (!$user->isTester) {
            $message = new AmplitudeEventStatisticsMessage('api.change_state', [], $user, $deviceId);
            $message->userOptions['state'] = $user->state;
            $this->bus->dispatch($message);
        }

        if ($invite) {
            $invite->registeredUser = $user;
            $this->inviteRepository->save($invite);
        }
        $this->eventDispatcher->dispatch(new PostRegistrationUserEvent($user));

        if ($userHasBeenInvited) {
            $this->eventDispatcher->dispatch(new UserInvitedEvent($user));
        }

        $amplitudeMessage = new AmplitudeEventStatisticsMessage('api.user_registered', [], $user, $deviceId);
        $amplitudeMessage->userOptions['utm_campaign'] = $user->utmCompaign ?? $user->source;
        $amplitudeMessage->userOptions['utm_source'] = $user->utmSource;
        $amplitudeMessage->userOptions['utm_content'] = $user->utmContent;

        if ($user->registeredByClubLink) {
            $amplitudeMessage->userOptions['clubSlug'] = $user->registeredByClubLink->slug;
        }

        $this->bus->dispatch($amplitudeMessage);

        return ['data' => $user];
    }
}
