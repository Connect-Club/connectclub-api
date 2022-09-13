<?php

namespace App\DTO\V1\VideoRoom;

class ScreenShareLinkResponse
{
    public string $link;

    public function __construct(string $link)
    {
        $this->link = $link;
    }
}
