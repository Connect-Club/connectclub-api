<?php

namespace App\Tests\Fixture;

use App\Entity\Follow\Follow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

trait FriendshipFixtureTrait
{
    private EntityManagerInterface $entityManager;

    private function makeFriends(User $user1, User $user2): void
    {
        $this->entityManager->persist(new Follow($user1, $user2));
        $this->entityManager->persist(new Follow($user2, $user1));
    }
}
