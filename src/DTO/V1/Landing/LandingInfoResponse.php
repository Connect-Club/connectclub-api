<?php

namespace App\DTO\V1\Landing;

use App\Entity\Landing\Landing;

class LandingInfoResponse
{
    /** @var string */
    public string $id;
    
    /** @var string */
    public string $name;

    /** @var string */
    public string $status;

    /** @var string */
    public string $url;

    /** @var string */
    public string $title;

    /** @var string|null */
    public ?string $subtitle;

    public function __construct(Landing $landing)
    {
        $this->id = $landing->id->toString();
        $this->name = $landing->name;
        $this->status = $landing->status;
        $this->url = $landing->url;
        $this->title = $landing->title;
        $this->subtitle = $landing->subtitle;
    }
}
