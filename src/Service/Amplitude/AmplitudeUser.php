<?php

namespace App\Service\Amplitude;

use App\Entity\User;

class AmplitudeUser
{
    private ?string $deviceId = null;
    private ?string $userId = null;
    private bool $isTester = false;

    private function __construct(?string $userId, ?string $deviceId, bool $isTester = false)
    {
        $this->userId = $userId;
        $this->deviceId = $deviceId;
        $this->isTester = $isTester;
    }

    public static function createFromUser(User $user): AmplitudeUser
    {
        return new self((string) $user->id, null, $user->isTester);
    }

    public static function createFromUserId(int $userId, bool $isTester = false): AmplitudeUser
    {
        return new self((string) $userId, null, $isTester);
    }

    public static function createFromDevice(string $device, bool $isTester = false): AmplitudeUser
    {
        return new self(null, $device, $isTester);
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function isTester(): bool
    {
        return $this->isTester;
    }
}
