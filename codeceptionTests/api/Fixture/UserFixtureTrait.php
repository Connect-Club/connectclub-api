<?php

namespace App\Tests\Fixture;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

trait UserFixtureTrait
{
    private EntityManagerInterface $entityManager;

    private function createUser(string $name, string $state = User::STATE_VERIFIED): User
    {
        $user = new User();
        $user->name = $name;
        $user->state = $state;

        $this->entityManager->persist($user);

        return $user;
    }

    private function createDeletedUser(string $name, string $state = User::STATE_VERIFIED): User
    {
        $user = $this->createUser($name, $state);

        $user->deletedAt = (new DateTime())->setTimestamp(time());

        return $user;
    }

    private function getUserRepository(): UserRepository
    {
        return $this->entityManager->getRepository(User::class);
    }
}
