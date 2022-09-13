<?php

namespace App\Doctrine;

use App\Entity\Activity\Activity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\ObjectHydrator;
use Doctrine\ORM\Query\ResultSetMapping;

class CursorPaginateHydrator extends ObjectHydrator
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    protected function hydrateAllData(): array
    {
        if (isset($this->_hints[CursorPaginateWalker::HINT_ENTITY_CLASS])
            &&
            isset($this->_hints[CursorPaginateWalker::HINT_LIMIT])) {
            $this->_rsm->addEntityResult($this->_hints[CursorPaginateWalker::HINT_ENTITY_CLASS], 'a', 'a');
            $this->_rsm->addScalarResult('row', 'row', Types::INTEGER);
        }

        $lastValue = null;

        $result = [];
        foreach (parent::hydrateAllData() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $lastValue = $item['row'];
            unset($item['row']);

            $result[] = count($item) == 1 ? array_values($item)[0] : $item;
        }

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('cnt', 'cnt', Types::INTEGER);

        $count = $this->_hints[CursorPaginateWalker::MAX_COUNT_HINT];
        if (!$result || $count == $lastValue) {
            $lastValue = null;
        }

        return [
            $result,
            $lastValue,
            $count
        ];
    }
}
