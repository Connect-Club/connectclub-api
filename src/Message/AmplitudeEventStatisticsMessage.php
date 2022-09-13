<?php

namespace App\Message;

use App\Entity\Community\Community;
use App\Entity\User;
use App\Service\Amplitude\AmplitudeUser;
use RuntimeException;
use Webmozart\Assert\Assert;

class AmplitudeEventStatisticsMessage
{
    /** @var string */
    public string $eventName;

    /** @var array */
    public array $eventOptions;

    /** @var array */
    public array $userOptions = [];

    /** @var string|null */
    public ?string $userIdentify = null;

    /** @var string|null */
    public ?string $deviceId = null;

    /** @var int|null */
    public ?int $communityId = null;

    public function __construct(
        string $eventName,
        array $eventOptions,
        $user = null,
        ?string $deviceId = null,
        ?Community $community = null
    ) {
        Assert::true($user || $deviceId, 'User or DeviceId must be set');

        $this->eventName = $eventName;
        $this->eventOptions = $eventOptions;

        if ($user instanceof User) {
            if ($user->isTester) {
                $this->userOptions['Is Tester'] ??= true;
            }
            $this->userIdentify = (string) $user->id;
        } elseif ($user instanceof AmplitudeUser) {
            if ($user->isTester()) {
                $this->userOptions['Is Tester'] ??= true;
            }
            $this->userIdentify = $user->getUserId();
            $this->deviceId = $user->getDeviceId();
        } else {
            if ($user !== null) {
                throw new RuntimeException(sprintf('Not supported class %s', get_class($user)));
            }
        }

        $this->deviceId ??= $deviceId;
        $this->communityId = $community ? $community->id : null;
    }
}
