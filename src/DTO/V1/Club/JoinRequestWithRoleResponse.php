<?php

namespace App\DTO\V1\Club;

use App\Entity\Club\JoinRequest;

class JoinRequestWithRoleResponse extends JoinRequestResponse
{
    /** @var string|null */
    public ?string $role = null;

    public function __construct(?string $role, JoinRequest $joinRequest)
    {
        $this->role = $role;

        parent::__construct($joinRequest);
    }
}
