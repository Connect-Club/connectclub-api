<?php

namespace App\Repository\Follow\Fetcher;

use App\Entity\User;
use Doctrine\ORM\NativeQuery;

class ResultWithCursorFetcher
{
    private int $limit;
    private array $cursors;

    public function __construct(int $limit, array $cursors)
    {
        $this->limit = $limit;
        $this->cursors = $cursors;
    }

    public function getResult(NativeQuery $query): array
    {
        $rawResult = $query->getResult();

        return [
            $this->getResultWithoutExtraItem($rawResult),
            $this->getResultLastValue($rawResult)
        ];
    }

    private function getResultLastValue(array $rawResult): ?string
    {
        $limit = $this->limit;

        if (count($rawResult) == $limit + 1) {
            $lastItem = $rawResult[$limit - 1];

            $lastValue = [];
            foreach ($this->cursors as $cursor) {
                $lastValue[] = $lastItem[$cursor];
            }
            $lastValue[] = $lastItem[0]->id;

            $lastValue = json_encode($lastValue);
        } else {
            $lastValue = null;
        }

        return $lastValue;
    }

    private function getResultWithoutExtraItem(array $rawResult): array
    {
        $extraItem = $rawResult[$this->limit] ?? null;

        $result = [];
        foreach ($rawResult as $item) {
            if ($extraItem !== null && $item === $extraItem) {
                break;
            }

            /** @var User $entity */
            $entity = $item[0];

            $result[] = $entity;
        }

        return $result;
    }
}
