<?php

namespace App\Service\Transaction;

interface Transaction
{
    public function up();

    public function down();
}
