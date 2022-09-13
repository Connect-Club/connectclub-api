<?php

namespace App\BulkInsert;

interface QueryInterface
{
    public function getRows(): array;
    public function getColumns(): array;
    public function getTableName(): string;
}
