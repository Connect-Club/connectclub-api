<?php

namespace App\DTO\V1;

class JabberPushRequest
{
    /** @var string */
    public string $id;
    /** @var string */
    public string $token;
    /** @var string */
    public string $type;
    /** @var string */
    public string $text;
    /** @var string */
    public string $from;
    /** @var string */
    public string $to;
    /** @var string */
    public string $sender;
    /** @var int */
    public int $count = 0;
    /** @var string|null */
    public ?string $translationCode = null;
    /** @var string|null */
    public ?string $translationPlaceholders = null;
}
