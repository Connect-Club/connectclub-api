<?php

namespace App\Entity\Interest;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Interest\InterestGroupRepository")
 */
class InterestGroup
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\Column(type="string") */
    public string $name;

    /**
     * @var Interest[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Interest\Interest", mappedBy="group", cascade={"all"})
     */
    public Collection $interests;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    public bool $isOld = true;

    /** @ORM\Column(type="integer", options={"default": 0}) */
    public int $globalSort = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->interests = new ArrayCollection();
    }

    /**
     * @return Interest[]|Collection
     */
    public function getInterests(): Collection
    {
        return $this->interests->matching(
            Criteria::create()->orderBy(['globalSort' => Criteria::DESC])
        );
    }
}
