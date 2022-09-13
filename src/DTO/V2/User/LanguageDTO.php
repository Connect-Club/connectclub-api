<?php

namespace App\DTO\V2\User;

use App\Entity\User\Language;

class LanguageDTO
{
    /** @var int */
    public int $id;

    /** @var string */
    public string $name;

    public function __construct(Language $language)
    {
        $this->id = (int) $language->id;
        $this->name = $language->name;
    }
}
