<?php

namespace App\Service\Transaction;

use App\Event\Transaction\PreRunTransactionManagerEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TransactionManager
{
    /** @var Transaction[] */
    private array $transactions = [];

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    /** @var string[] */
    private array $ignoreExceptions = [];

    /**
     * TransactionManager constructor.
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function createEmpty(): self
    {
        $manager = clone $this;

        $manager->transactions = [];
        $manager->ignoreExceptions = [];

        return $manager;
    }

    public function merge(TransactionManager $transactionManager): self
    {
        foreach ($transactionManager->getTransactions() as $transaction) {
            $this->addTransaction($transaction);
        }

        foreach ($transactionManager->ignoreExceptions as $ignoreException) {
            $this->ignoreExceptions[] = $ignoreException;
        }

        return $this;
    }

    public function addNamedTransaction(string $transactionName, $transaction)
    {
        if ($transaction instanceof Transaction) {
            $this->transactions[$transactionName] = $transaction;
        } elseif (is_callable($transaction)) {
            $this->transactions[$transactionName] = new CallbackTransaction($transaction);
        } else {
            throw new \RuntimeException('Expected type Transaction or callable, got '.gettype($transaction));
        }

        return $this;
    }

    public function addTransaction($transaction): self
    {
        if ($transaction instanceof Transaction) {
            $name = spl_object_hash($transaction);
        } elseif (is_callable($transaction)) {
            $transaction = new CallbackTransaction($transaction);
            $name = spl_object_hash($transaction);
        } else {
            throw new \RuntimeException('Expected type Transaction or callable, got '.gettype($transaction));
        }

        $this->addNamedTransaction($name, $transaction);

        return $this;
    }

    public function addIgnoreException($exception): self
    {
        $this->ignoreExceptions[] = $exception;

        return $this;
    }

    public function run()
    {
        $this->eventDispatcher->dispatch(new PreRunTransactionManagerEvent($this));

        /** @var Transaction[] $completedTransactions */
        $completedTransactions = [];
        /** @var StopwatchEvent[] $stopwatchEvents */
        $stopwatchEvents = [];

        $stopwatch = new Stopwatch();

        foreach ($this->transactions as $i => $transaction) {
            $stopwatchEventCode = basename(str_replace('\\', '/', get_class($transaction))).'#'.$i;
            $stopwatch->start($stopwatchEventCode);

            try {
                $transaction->up();
                $completedTransactions[] = $transaction;
            } catch (\Exception $exception) {
                $isIgnoreException = false;

                foreach ($this->ignoreExceptions as $ignoreException) {
                    if (get_class($exception) == $ignoreException) {
                        $isIgnoreException = true;
                    }
                }

                if (!$isIgnoreException) {
                    foreach ($completedTransactions as $completedTransaction) {
                        try {
                            $this->logger->info('Start rollback transaction '.get_class($completedTransaction));
                            $completedTransaction->down();
                        } catch (\Exception $exceptionDown) {
                            $this->logger->error(sprintf(
                                'Rollback transaction %s error %s',
                                get_class($completedTransaction),
                                $exceptionDown->getMessage()
                            ), ['exception' => $exceptionDown]);
                        }
                    }

                    $this->transactions = [];

                    throw $exception;
                } else {
                    $this->logger->debug('Ignore exception '.get_class($exception));
                }
            }

            $stopwatchEvents[$stopwatchEventCode] = $stopwatch->stop($stopwatchEventCode);
        }

        $duration = array_sum(array_map(fn(StopwatchEvent $e) => $e->getDuration(), $stopwatchEvents)) / 1000;
        if ($duration > 2) {
            $debug = implode(
                PHP_EOL,
                array_map(
                    fn(string $eventName, StopwatchEvent $e) => $eventName.':'.$e,
                    array_keys($stopwatchEvents),
                    $stopwatchEvents
                )
            );
            $this->logger->warning('Duration transactions '.$duration.' '.$debug);
        }

        $this->transactions = [];
    }
}
