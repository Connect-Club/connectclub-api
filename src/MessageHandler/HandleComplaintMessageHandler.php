<?php

namespace App\MessageHandler;

use App\Client\RtpAudioClient;
use App\Message\HandleComplaintMessage;
use App\Repository\UserRepository;
use App\Service\SlackClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Throwable;

final class HandleComplaintMessageHandler implements MessageHandlerInterface
{
    private RtpAudioClient $rtpAudioClient;
    private UserRepository $userRepository;
    private MessageBusInterface $bus;
    private SlackClient $slackClient;
    private LoggerInterface $logger;

    public function __construct(
        RtpAudioClient $rtpAudioClient,
        UserRepository $userRepository,
        MessageBusInterface $bus,
        SlackClient $slackClient,
        LoggerInterface $logger
    ) {
        $this->rtpAudioClient = $rtpAudioClient;
        $this->userRepository = $userRepository;
        $this->bus = $bus;
        $this->slackClient = $slackClient;
        $this->logger = $logger;
    }

    public function __invoke(HandleComplaintMessage $message)
    {
        try {
            $this->handle($message);
        } catch (Throwable $exception) {
            $this->logger->error($exception, ['exception' => $exception]);
        }
    }

    private function handle(HandleComplaintMessage $message)
    {
        $slackChannel = $_ENV['SLACK_CHANNEL_COMPLAINT_NAME'];

        $user = $this->userRepository->find($message->userId);
        if (!$user) {
            return;
        }

        if ($message->attempt >= 10) {
            $this->logger->warning('Waiting limit recognition', [
                'userId' => $message->userId,
                'conferenceId' => $message->conferenceId,
                'attempt' => $message->attempt,
            ]);

            return;
        }

        if ($message->delayedRequestId === null) {
            if ($user->country && in_array($user->country->isoCode, ['RU','UA','MD','KZ','BY'])) {
                $language = 'ru-RU';
            } else {
                $language = 'en-US';
            }

            $data = $this->rtpAudioClient->startSpeechRecognition($message->conferenceId, $message->userId, $language);
            $message->delayedRequestId = $data['RequestId'];
            $message->language = $language;

            $this->bus->dispatch($message, [new DelayStamp(2000)]);
        } else {
            $data = $this->rtpAudioClient->checkSpeechRecognition($message->delayedRequestId);

            if (isset($data['Error']) && !empty($data['Error'])) {
                $this->slackClient->sendMessage($slackChannel, $data['Error'], $message->threadTsSlack, false);
            } elseif (isset($data['RecognitionResults']) && !empty($data['RecognitionResults'])) {
                $textMessage = sprintf('Last speech audio: %s', $data['AudioUri']);
                $textMessage .= PHP_EOL;

                foreach ($data['RecognitionResults'] as $recognitionResult) {
                    $recognitionResult = $recognitionResult['alternatives'][0];
                    $textMessage .= 'Recognition text: '.$recognitionResult['transcript'] ?? '';
                    $textMessage .= PHP_EOL;
                    $textMessage .= 'Confidence: '.$recognitionResult['confidence'] ?? '';
                    $textMessage .= PHP_EOL;
                    $textMessage .= 'Language: '.$message->language ?? '';
                    $textMessage .= PHP_EOL;
                }

                $this->slackClient->sendMessage($slackChannel, $textMessage, $message->threadTsSlack, false);
            } else {
                $message->attempt += 1;
                $this->bus->dispatch($message, [new DelayStamp(pow(2, $message->attempt) * 1000)]);
            }
        }
    }
}
