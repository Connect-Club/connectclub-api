<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\UpdateTelegramEventMessage;
use App\Repository\UserRepository;
use App\Service\PhoneNumberManager;
use App\Service\SMS\TwoFactorAuthenticatorManager;
use libphonenumber\PhoneNumberUtil;
use Redis;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Contact;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\ReplyKeyboardRemove;
use TelegramBot\Api\Types\Update;
use Throwable;

final class UpdateTelegramEventMessageHandler implements MessageHandlerInterface
{
    private UserRepository $userRepository;
    private BotApi $telegramBotClient;
    private EventDispatcherInterface $dispatcher;
    private PhoneNumberManager $phoneNumberManager;
    private Redis $redis;
    private TwoFactorAuthenticatorManager $authenticatorManager;

    public function __construct(
        UserRepository $userRepository,
        BotApi $telegramBotClient,
        EventDispatcherInterface $eventDispatcher,
        PhoneNumberManager $phoneNumberManager,
        TwoFactorAuthenticatorManager $authenticatorManager,
        Redis $redis
    ) {
        $this->userRepository = $userRepository;
        $this->telegramBotClient = $telegramBotClient;
        $this->dispatcher = $eventDispatcher;
        $this->phoneNumberManager = $phoneNumberManager;
        $this->authenticatorManager = $authenticatorManager;
        $this->redis = $redis;
    }

    public function __invoke(UpdateTelegramEventMessage $message)
    {
        $update = Update::fromResponse($message->getUpdate());

        if ($callbackQuery = $update->getCallbackQuery()) {
            $from = $callbackQuery->getFrom();
        } elseif ($inlineQuery = $update->getInlineQuery()) {
            $from = $inlineQuery->getFrom();
        } else {
            $from = $update->getMessage()->getFrom();
        }

        $fromId = $from->getId();

        $user = $this->userRepository->findOneBy(['telegramId' => $fromId]);
        if (!$user) {
            $user = $this->tryAuthorizeUser($fromId, $update);
            if ($user) {
                $this->telegramBotClient->sendMessage($fromId, 'Hi '.$user->username);
            }
        }
    }

    private function tryAuthorizeUser(int $fromId, Update $update): ?User
    {
        $text = mb_strtolower(trim($update->getMessage()->getText()));

        /** @var Contact|null $contact */
        $contact = $update->getMessage()->getContact();
        if ($text == '/start' && !$contact) {
            $this->telegramBotClient->sendMessage(
                $fromId,
                'Please provide your username or phone number of CC account or share current phone number',
                null,
                false,
                null,
                new ReplyKeyboardMarkup([
                    [
                        ['text' => 'Share phone number', 'request_contact' => true]
                    ],
                ]),
                true
            );

            return null;
        } else {
            if ($contact && $user = $this->tryAuthorizeUserByContact($fromId, $update)) {
                $user->telegramId = (string) $fromId;
                $this->userRepository->save($user);
            } else {
                if ($this->getUserOption('state', $fromId) === 'authorization_code_send') {
                    if ($user = $this->tryAuthorizeUserByCode($fromId, $update)) {
                        $this->setUserOption('state', $fromId, 'authorized');
                        $user->telegramId = (string) $fromId;
                        $this->userRepository->save($user);
                    }
                } else {
                    $this->startAuthorizeUserByPhoneNumber($update, $fromId);
                }
            }

            return $user ?? null;
        }
    }

    private function tryAuthorizeUserByContact(int $fromId, Update $update): ?User
    {
        /** @var Contact|null $contact */
        $contact = $update->getMessage()->getContact();
        if (!$contact) {
            return null;
        }

        if (!$contact->getPhoneNumber()) {
            return null;
        }

        if ($contact->getUserId() !== $fromId) {
            return null;
        }

        $phone = null;
        try {
            $phone = $this->phoneNumberManager->parse($contact->getPhoneNumber());
        } catch (Throwable $e) {
        }

        if (!$phone) {
            $this->telegramBotClient->sendMessage(
                $fromId,
                '🚫 Access and parse your phone number error. Please send your phone number as text.',
                null,
                false,
                null,
                new ReplyKeyboardRemove()
            );
            return null;
        }

        $user = $this->userRepository->findOneBy(['phone' => $phone]);

        if ($user) {
            $this->telegramBotClient->sendMessage(
                $fromId,
                '✅ Success authorization as '.$user->username,
                null,
                false,
                null,
                new ReplyKeyboardRemove()
            );
        } else {
            $this->telegramBotClient->sendMessage(
                $fromId,
                '🚫 User with phone number '.$contact->getPhoneNumber().' not found',
                null,
                false,
                null,
                new ReplyKeyboardRemove()
            );
        }

        return $user;
    }

    private function tryAuthorizeUserByCode(int $fromId, Update $update): ?User
    {
        $code = trim($update->getMessage()->getText());

        if (!$code || mb_strlen($code) != 4) {
            $this->telegramBotClient->sendMessage($fromId, '🚫 Code length must be 4 digits.');
            return null;
        }

        $phoneNumberString = $this->getUserOption('phoneNumber', $fromId);
        $phoneNumberObject = $this->phoneNumberManager->parse(
            $phoneNumberString,
        );

        $authorized = $this->authenticatorManager->checkVerificationCode(
            $phoneNumberObject,
            $code
        );

        if ($authorized) {
            $user = $this->userRepository->findOneBy(['phone' => $phoneNumberObject]);
            if ($user) {
                $this->telegramBotClient->sendMessage($fromId, '✅ You successfully authorized as '.$user->username);
                $this->setUserOption('state', $fromId, 'authorized');
                return $user;
            } else {
                $this->telegramBotClient->sendMessage(
                    $fromId,
                    '⏸ First you should register in app and after you can use this bot.'
                );
                $this->setUserOption('state', $fromId, 'null');
                return null;
            }
        } else {
            $this->telegramBotClient->sendMessage(
                $fromId,
                '🚫 Your code is incorrect'
            );
            return null;
        }
    }

    private function startAuthorizeUserByPhoneNumber(Update $update, int $fromId): void
    {
        $text = mb_strtolower(trim($update->getMessage()->getText()));

        $detectedPhone = null;
        try {
            $detectedPhone = $this->phoneNumberManager->parse($text);
        } catch (Throwable $exception) {
        }

        if (!$detectedPhone) {
            $phoneNumbers = $this->phoneNumberManager
                ->getPhoneNumberUtil()
                ->findNumbers($text, PhoneNumberUtil::UNKNOWN_REGION);
            if ($phoneNumbers->current()) {
                $detectedPhone = $phoneNumbers->current()->number();
            }
        }

        if (!$detectedPhone) {
            $this->telegramBotClient->sendMessage($fromId, 'I except phone number for authorize you in bot');
            return;
        }

        $this->authenticatorManager->sendVerificationRequest($detectedPhone);

        $this->telegramBotClient->sendMessage(
            $fromId,
            '☎️We send you 4 digit code on your phone for authorize in app. Provide it:'
        );

        $this->setUserOption(
            'phoneNumber',
            $fromId,
            $this->phoneNumberManager->formatE164($detectedPhone)
        );
        $this->setUserOption('state', $fromId, 'authorization_code_send');
    }

    private function getUserOption(string $option, int $fromId)
    {
        return $this->redis->get('telegram_bot_'.$fromId.'_'.$option);
    }

    private function setUserOption(string $option, int $fromId, string $state)
    {
        $this->redis->set('telegram_bot_'.$fromId.'_'.$option, $state);
        $this->redis->expire('telegram_bot_'.$fromId.'_state', 60);
    }
}
