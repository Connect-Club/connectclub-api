<?php

namespace App\EventSubscriber\Transaction;

use App\Event\Transaction\PreRunTransactionManagerEvent;
use App\Service\Transaction\CommittableTransaction;
use App\Transaction\FlushEntityManagerTransaction;
use App\Transaction\FlushRemoveManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PreRunTransactionManagerSubscriber implements EventSubscriberInterface
{
    public function onPreRunTransactionManager(PreRunTransactionManagerEvent $preRunTransactionManagerEvent)
    {
        $transactionManager = $preRunTransactionManagerEvent->getTransactionManager();

        foreach ($transactionManager->getTransactions() as $transaction) {
            if ($transaction instanceof CommittableTransaction) {
                $transactionManager->addTransaction(fn() => $transaction->commit());
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [PreRunTransactionManagerEvent::class => 'onPreRunTransactionManager'];
    }
}
