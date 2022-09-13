<?php

namespace App\DTO\V1\Subscription;

use Symfony\Component\Validator\Constraints as Assert;

class ChartRequest
{
    /**
     * @Assert\NotBlank
     * @Assert\Type("numeric")
     */
    public string $dateStart;

    /**
     * @Assert\NotBlank
     * @Assert\Type("numeric")
     */
    public string $dateEnd;

    /** @Assert\NotBlank */
    public string $timeZone;

    /**
     * @Assert\Choice({"day", "month"})
     */
    public string $overview = 'day';

    /**
     * @Assert\Choice({"quantity", "sum"})
     */
    public string $type = 'quantity';
}
