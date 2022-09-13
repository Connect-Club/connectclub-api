<?php

namespace App\DTO\V1\Subscription;

class ChartResponse
{
    public int $dateStart;
    public int $dateEnd;

    /** @var ChartValue[] */
    public array $values;

    public function __construct(array $chartData)
    {
        $this->dateStart = $chartData['minDate'];
        $this->dateEnd = $chartData['maxDate'];

        $this->values = [];
        foreach ($chartData['values'] as $value) {
            $this->values[] = new ChartValue($value['date'], $value['value']);
        }
    }
}
