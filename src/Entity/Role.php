<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table("users_roles")
 * @ORM\Entity(repositoryClass="App\Repository\RoleRepository")
 */
class Role
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPERCREATOR = 'supercreator';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    public ?int $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="roles") */
    public User $user;

    /** @ORM\Column(type="string") */
    public string $role;

    public function __construct(User $user, string $role)
    {
        $this->user = $user;
        $this->role = $role;
    }
}
