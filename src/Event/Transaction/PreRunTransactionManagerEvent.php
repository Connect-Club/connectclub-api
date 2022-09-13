<?php

namespace App\Event\Transaction;

use App\Service\Transaction\TransactionManager;
use Symfony\Contracts\EventDispatcher\Event;

class PreRunTransactionManagerEvent extends Event
{
    private TransactionManager $transactionManager;

    public function __construct(TransactionManager $transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    public function getTransactionManager(): TransactionManager
    {
        return $this->transactionManager;
    }
}
