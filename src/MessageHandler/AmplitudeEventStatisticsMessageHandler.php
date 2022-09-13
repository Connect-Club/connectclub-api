<?php

namespace App\MessageHandler;

use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\Community\CommunityRepository;
use App\Service\Amplitude\AmplitudeManager;
use App\Service\Amplitude\AmplitudeTooManyRequestsException;
use App\Service\AmplitudeDataManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Redis;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class AmplitudeEventStatisticsMessageHandler implements MessageHandlerInterface
{
    private const NO_SESSION = -1;

    private AmplitudeManager $amplitudeManager;
    private MessageBusInterface $bus;
    private AmplitudeDataManager $amplitudeDataManager;

    public function __construct(
        AmplitudeDataManager $amplitudeDataManager,
        AmplitudeManager $amplitudeManager,
        MessageBusInterface $bus
    ) {
        $this->amplitudeDataManager = $amplitudeDataManager;
        $this->amplitudeManager = $amplitudeManager;
        $this->bus = $bus;
    }

    public function __invoke(AmplitudeEventStatisticsMessage $amplitudeEventStatisticsMessage)
    {
        $userId = $amplitudeEventStatisticsMessage->userIdentify;

        $deviceId = null;
        $sessionId = self::NO_SESSION;
        $userId = $userId ? (int) $userId : null;
        if ($userId) {
            $deviceId = $this->amplitudeDataManager->getDeviceId($userId);
            $sessionId = $this->amplitudeDataManager->getSessionId($userId) ?? self::NO_SESSION;
        }

        try {
            $this->amplitudeManager->sendEventsRequestToAmplitude([
                [
                    'user_id' => (string) $userId,
                    'event_type' => $amplitudeEventStatisticsMessage->eventName,
                    'time' => round(microtime(true) * 1000), //microtime as integer
                    'event_properties' => $amplitudeEventStatisticsMessage->eventOptions,
                    'user_properties' => $amplitudeEventStatisticsMessage->userOptions,
                    'insert_id' => Uuid::uuid4()->toString(),
                    'device_id' => $amplitudeEventStatisticsMessage->deviceId ?? $deviceId ?? Uuid::uuid4()->toString(),
                    'session_id' => $sessionId
                ],
            ]);
        } catch (AmplitudeTooManyRequestsException $amplitudeTooManyRequestsException) {
            $this->bus->dispatch($amplitudeEventStatisticsMessage, [new DelayStamp(5000)]);
        }
    }
}
