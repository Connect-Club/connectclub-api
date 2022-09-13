<?php

namespace App\Service;

use Redis;

class AmplitudeDataManager
{
    private Redis $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function saveSessionId(int $userId, $sessionId): void
    {
        $this->set($userId, 'sessionId', (int) $sessionId);
    }

    public function saveDeviceId(int $userId, string $deviceId): void
    {
        $this->set($userId, 'deviceId', $deviceId);
    }

    public function saveAppVersion(int $userId, array $version): void
    {
        $this->set($userId, 'appVersion', json_encode($version));
    }

    public function getSessionId(int $userId): ?int
    {
        $sessionId = $this->get($userId, 'sessionId');

        return $sessionId === false ? null : (int) $sessionId;
    }

    public function getDeviceId(int $userId): ?string
    {
        $deviceId = $this->get($userId, 'deviceId');

        return $deviceId === false ? null : $deviceId;
    }

    public function getAppVersion(int $userId): ?array
    {
        $version = $this->get($userId, 'appVersion');

        return $version === false ? null : json_decode($version);
    }

    public function deleteAppVersion(int $userId): void
    {
        $this->del($userId, 'appVersion');
    }

    /** @return mixed */
    private function get(int $userId, string $key)
    {
        return $this->redis->get("user:$userId:amplitude:$key");
    }

    private function set(int $userId, string $key, $value): void
    {
        $this->redis->set("user:$userId:amplitude:$key", $value);
    }

    private function del(int $userId, string $key): void
    {
        $this->redis->del("user:$userId:amplitude:$key");
    }
}
