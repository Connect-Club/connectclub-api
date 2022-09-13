<?php

namespace App\Service;

use App\Entity\Log\EventLog;
use App\Entity\Log\LoggableEntityInterface;
use App\Entity\Log\LoggableRelatedDependencyInterface;
use App\Repository\Log\EventLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class EventLogManager
{
    private EventLogRepository $eventLogRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EventLogRepository $eventLogRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->eventLogRepository = $eventLogRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function logEvent(LoggableEntityInterface $entity, string $eventCode, array $context = [])
    {
        try {
            $this->processLogEvent($entity, $eventCode, $context);
        } catch (Throwable $exception) {
            $this->logger->error($exception, ['exception' => $exception]);
        }
    }

    public function logEventCustomObject(string $eventCode, string $entityCode, string $entityId, array $context = [])
    {
        $log = new EventLog($entityCode, $entityId, $eventCode, $context);
        $this->eventLogRepository->save($log);

        $this->logger->debug(sprintf(
            'Log event object %s %s %s %s',
            $eventCode,
            $entityCode,
            $entityId,
            json_encode($context)
        ));
    }

    private function processLogEvent(LoggableEntityInterface $entity, string $eventCode, array $context = [])
    {
        $entityId = $this->getEntityId($entity);

        $log = new EventLog($entity->getEntityCode(), $entityId, $eventCode, $context);

        if ($entity instanceof LoggableRelatedDependencyInterface) {
            foreach ($entity->getDependencies() as $dependency) {
                if (!$dependency instanceof LoggableEntityInterface) {
                    continue;
                }
                $dependencyId = $this->getEntityId($dependency);
                $log->addRelation($dependency->getEntityCode(), $dependencyId);
            }
        }

        $this->eventLogRepository->save($log);
    }

    private function getEntityId(object $entity): ?string
    {
        $identifiers = $this->entityManager
            ->getClassMetadata(get_class($entity))
            ->getIdentifierValues($entity);

        $id = array_values($identifiers)[0] ?? null;

        return $id ? (string) $id : null;
    }
}
