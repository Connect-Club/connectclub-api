<?php

namespace App\Service;

use Doctrine\ORM\Query;

interface PaginationQueryPreProcessor
{
    public function process(Query $query): Query;
}
