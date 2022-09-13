<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Event\PreRegistrationUserEvent;
use App\Repository\Interest\InterestRepository;
use App\Repository\Location\CityRepository;
use InvalidArgumentException;
use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FillGeoDataUserSubscriber implements EventSubscriberInterface
{
    private Reader $reader;
    private RequestStack $requestStack;
    private CityRepository $cityRepository;
    private InterestRepository $interestRepository;
    private LoggerInterface $logger;

    public function __construct(
        Reader $reader,
        RequestStack $requestStack,
        CityRepository $cityRepository,
        InterestRepository $interestRepository,
        LoggerInterface $logger
    ) {
        $this->reader = $reader;
        $this->requestStack = $requestStack;
        $this->cityRepository = $cityRepository;
        $this->interestRepository = $interestRepository;
        $this->logger = $logger;
    }

    public function onPreRegistrationUser(PreRegistrationUserEvent $preRegistrationUserEvent)
    {
        $ip = $this->requestStack->getCurrentRequest()->getClientIp();
        if (!$ip) {
            return;
        }

        try {
            $locationData = $this->reader->get($ip);
        } catch (Reader\InvalidDatabaseException $invalidDatabaseException) {
            $this->logger->error('MixMind error fetching ip info: '.$invalidDatabaseException->getMessage(), [
                'exception' => $invalidDatabaseException,
                'ip' => $ip,
            ]);

            return;
        } catch (InvalidArgumentException $exception) {
            return;
        }

        $cityId = $locationData['city']['geoname_id'] ?? null;
        if (!$cityId) {
            $this->logger->warning('Cannot detect location info from ip '.$ip);

            return;
        }

        if ($city = $this->cityRepository->find($cityId)) {
            $preRegistrationUserEvent->user->city = $city;
            $preRegistrationUserEvent->user->country = $city->country;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreRegistrationUserEvent::class => 'onPreRegistrationUser',
        ];
    }
}
