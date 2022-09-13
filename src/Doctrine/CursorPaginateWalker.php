<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\SqlWalker;

class CursorPaginateWalker extends SqlWalker
{
    const HINT_LAST_VALUE = 'cursorPaginateWalker.lastValue';
    const MAX_COUNT_HINT = 'cursorPaginateWalker.maxCount';
    const HINT_LIMIT = 'cursorPaginateWalker.limit';
    const HINT_ENTITY_CLASS = 'cursorPaginateWalker.entityClass';
    const HINT_ORDER_BY = 'cursorPaginateWalker.orderBy';
    const HINT_ORDER_BY_FIELD = 'cursorPaginateWalker.orderByField';

    public function walkSelectStatement(SelectStatement $AST): string
    {
        $sql = parent::walkSelectStatement($AST);

        $lastValue = $this->getQuery()->getHint(self::HINT_LAST_VALUE);
        if (!$lastValue) {
            $lastValue = 0;
        }

        $limit = $this->getQuery()->getHint(self::HINT_LIMIT) ?? 20;
        $orderBy = $this->getQuery()->getHint(self::HINT_ORDER_BY);
        $orderByField = $this->getQuery()->getHint(self::HINT_ORDER_BY_FIELD);

        $windowedFunction = null;
        if ($orderBy && $orderByField) {
            $windowedFunction = 'ORDER BY q.'.$orderByField.' '.$orderBy;
        }

        $sql =  'SELECT * FROM (
                    SELECT *, DENSE_RANK() OVER ('.$windowedFunction.') AS row 
                    FROM ('.$sql.') q
                 ) q2
                 WHERE q2.row > '.$lastValue.' AND q2.row <= '.($lastValue + $limit);

        return $sql;
    }
}
