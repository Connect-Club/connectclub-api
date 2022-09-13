<?php

namespace App\Message;

class InviteAllNetworkToClubMessage
{
    private string $clubId;
    private int $authorId;
    private int $lastValue = 0;

    public function __construct(string $clubId, int $authorId)
    {
        $this->clubId = $clubId;
        $this->authorId = $authorId;
    }

    public function getLastValue(): ?int
    {
        return $this->lastValue;
    }

    public function setLastValue(int $lastValue): self
    {
        $this->lastValue = $lastValue;

        return $this;
    }

    public function getClubId(): string
    {
        return $this->clubId;
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }
}
