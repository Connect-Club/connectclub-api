<?php

namespace App\DTO\V1\User;

class UserBanDeleteRequest
{
    /** @var string|null */
    public $comment = null;
    /** @var bool|null */
    public ?bool $cleanup = null;
}
