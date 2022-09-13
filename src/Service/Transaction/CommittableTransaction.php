<?php

namespace App\Service\Transaction;

interface CommittableTransaction
{
    public function commit();
}
