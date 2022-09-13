<?php

namespace App\DTO\V2\Interests;

use App\Entity\Interest\Interest;

class InterestDTO
{
    /** @var int */
    public int $id = 0;

    /** @var string */
    public string $name = '';

    /** @var bool */
    public bool $isLanguage = false;

    public function __construct(?Interest $interest = null)
    {
        if ($interest) {
            $this->id = (int) $interest->id;
            $this->name = $interest->name;
        }
    }

    public static function createFromFields(int $id, string $name, bool $isLanguage): InterestDTO
    {
        $new = new self();

        $new->id = $id;
        $new->name = $name;
        $new->isLanguage = $isLanguage;

        return $new;
    }
}
