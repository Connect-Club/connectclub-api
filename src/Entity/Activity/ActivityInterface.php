<?php

namespace App\Entity\Activity;

use App\Entity\User;
use Ramsey\Uuid\UuidInterface;
use Doctrine\Common\Collections\Collection;

interface ActivityInterface
{
    public function getId(): UuidInterface;
    public function getUser(): ?User;
    public function getNestedUsers(): Collection;
    public function getCreatedAt(): int;
    public function getReadAt(): ?int;
    public function setReadAt(?int $readAt): void;
    public function getType(): string;
}
