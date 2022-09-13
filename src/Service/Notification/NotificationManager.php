<?php

namespace App\Service\Notification;

use App\Entity\Community\Community;
use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Message\SendNotificationMessage;
use App\Message\SendNotificationMessageBatch;
use App\Repository\Notification\NotificationRepository;
use App\Repository\User\DeviceRepository;
use App\Service\Notification\Push\PushNotification;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationManager
{
    private MessageBusInterface $bus;
    private TranslatorInterface $translator;

    const MODE_SERIAL = 1;
    const MODE_BATCH = 2;

    private array $translationParameters = [];
    private DeviceRepository $deviceRepository;
    private ?Community $notificationForCommunity = null;
    private ?User\Device $specificDevice = null;
    private int $mode = self::MODE_SERIAL;
    private array $batches = [];
    private array $preparedDevices = [];

    public function __construct(
        DeviceRepository $deviceRepository,
        MessageBusInterface $bus,
        TranslatorInterface $translator
    ) {
        $this->deviceRepository = $deviceRepository;
        $this->bus = $bus;
        $this->translator = $translator;
    }

    public function setMode(int $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function addTranslationParameter(string $parameter, $value): void
    {
        $this->translationParameters[$parameter] = $value;
    }

    public function forDevice(User\Device $device)
    {
        $this->specificDevice = $device;
    }

    public function notificationForCommunity(Community $community): self
    {
        $this->notificationForCommunity = $community;

        return $this;
    }

    public function sendNotifications(User $participant, PushNotification ...$notifications)
    {
        if ($this->notificationForCommunity && $this->notificationForCommunity->isMuteFor($participant)) {
            return;
        }

        if ($participant->skipNotificationUntil && $participant->skipNotificationUntil >= time()) {
            return;
        }

        foreach ($notifications as $notification) {
            $devices = $this->specificDevice ? [$this->specificDevice] : $this->findDevices($participant);

            foreach ($devices as $device) {
                if (!$notification->supportDevice($device)) {
                    continue;
                }

                $message = $notification->getMessage()->getMessage();
                $parameters = $notification->getMessage()->getMessageParameters();

                if (isset($parameters['badge'])) {
                    $parameters['badge'] = (string) $parameters['badge'];
                }

                $translationParameters = $notification->getPredefinedTranslationParameters();
                foreach ($translationParameters as $k => $translationParameter) {
                    if ($translationParameter instanceof SpecificTranslationParameterInterface) {
                        $translationParameter->forDevice($device);
                        $translationParameters[$k] = (string) $translationParameter;
                    }
                }

                if (isset($parameters['title'])) {
                    $parameters['title'] = $this->translator->trans(
                        $parameters['title'],
                        array_merge($this->translationParameters, $translationParameters)
                    );
                }

                $event = new SendNotificationMessage(
                    new Notification($device, $notification),
                    $device->token,
                    $device->type,
                    $message ? $this->translator->trans(
                        $message,
                        array_merge($this->translationParameters, $translationParameters)
                    ) : null,
                    $parameters,
                );

                if ($this->mode === self::MODE_BATCH) {
                    $this->batches[] = $event;
                } else {
                    $this->bus->dispatch($event);
                }
            }
        }
    }

    public function prepareDeviceTokensForParticipants(array $participants)
    {
        $devices = $this->deviceRepository->findDevicesOfUserIds($participants);
        foreach ($devices as $device) {
            $this->preparedDevices[$device->user->id] ??= [];
            $this->preparedDevices[$device->user->id][] = $device;
        }
    }

    public function flushBatch(): void
    {
        if ($this->batches) {
            $this->bus->dispatch(new SendNotificationMessageBatch($this->batches));
            $this->batches = [];
            $this->preparedDevices = [];
        }
    }

    private function findDevices(User $user): Collection
    {
        if (isset($this->preparedDevices[$user->id])) {
            return new ArrayCollection($this->preparedDevices[$user->id]);
        }

        return $user->devices->filter(fn(User\Device $d) => $d->token !== null);
    }
}
