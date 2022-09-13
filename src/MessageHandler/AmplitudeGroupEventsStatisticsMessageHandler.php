<?php

namespace App\MessageHandler;

use App\Message\AmplitudeGroupEventsStatisticsMessage;
use App\Service\Amplitude\AmplitudeManager;
use App\Service\Amplitude\AmplitudeTooManyRequestsException;
use App\Service\AmplitudeDataManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class AmplitudeGroupEventsStatisticsMessageHandler implements MessageHandlerInterface
{
    private AmplitudeDataManager $amplitudeDataManager;
    private AmplitudeManager $amplitudeManager;
    private MessageBusInterface $bus;

    public function __construct(
        AmplitudeDataManager $amplitudeDataManager,
        AmplitudeManager $amplitudeManager,
        MessageBusInterface $bus
    ) {
        $this->amplitudeDataManager = $amplitudeDataManager;
        $this->amplitudeManager = $amplitudeManager;
        $this->bus = $bus;
    }

    public function __invoke(AmplitudeGroupEventsStatisticsMessage $message)
    {
        $batch = $message->getBatch();

        $events = [];

        foreach ($batch as $amplitudeEventStatisticsMessage) {
            $userId = $amplitudeEventStatisticsMessage->userIdentify;

            $deviceId = null;
            $userId = $userId ? (int) $userId : null;
            $sessionId = -1;
            if ($userId) {
                $deviceId = $this->amplitudeDataManager->getDeviceId($userId);
                $sessionId = $this->amplitudeDataManager->getSessionId($userId) ?? -1;
            }

            $events[] = [
                'user_id' => (string) $userId,
                'event_type' => $amplitudeEventStatisticsMessage->eventName,
                'time' => round(microtime(true) * 1000), //microtime as integer
                'event_properties' => $amplitudeEventStatisticsMessage->eventOptions,
                'user_properties' => $amplitudeEventStatisticsMessage->userOptions,
                'insert_id' => Uuid::uuid4()->toString(),
                'device_id' => $amplitudeEventStatisticsMessage->deviceId ?? $deviceId ?? Uuid::uuid4()->toString(),
                'session_id' => $sessionId
            ];
        }

        if ($events) {
            try {
                $this->amplitudeManager->sendEventsRequestToAmplitude($events);
            } catch (AmplitudeTooManyRequestsException $amplitudeTooManyRequestsException) {
                $this->bus->dispatch($message, [new DelayStamp(5000)]);
            }
        }
    }
}
