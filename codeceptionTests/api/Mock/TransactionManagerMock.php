<?php

namespace App\Tests\Mock;

use App\Service\Transaction\TransactionManager;

class TransactionManagerMock extends TransactionManager
{
    private ?\Throwable $exception = null;

    public function run()
    {
        $exception = $this->exception;
        if ($exception) {
            $this->addTransaction(function () use ($exception) {
                throw $exception;
            });
        }

        parent::run();
    }

    public function throwExceptionBeforeCommit(?\Throwable $exception = null)
    {
        $this->exception = $exception;
    }
}
