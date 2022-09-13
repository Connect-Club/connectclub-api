<?php

namespace App\DTO\V1\Club;

use App\Entity\Club\Club;
use Symfony\Component\Serializer\Annotation\Groups;

class ClubSlimResponse
{
    /**
     * @var string
     */
    public string $id;

    /**
     * @var string
     */
    public string $slug;

    /**
     * @var string
     */
    public string $title;

    /**
     * @var int
     */
    public int $createdAt;

    public function __construct(Club $club)
    {
        $this->id = $club->id->toString();
        $this->title = $club->title;
        $this->slug = $club->slug;
        $this->createdAt = $club->createdAt;
    }
}
