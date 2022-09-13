<?php

namespace App\DTO\V1\Reference;

use App\Entity\Matching\Skill;
use App\Entity\Matching\SkillCategory;

class SkillCategoryResponse extends ReferenceResponse
{
    /** @var ReferenceResponse[] */
    public array $skills = [];

    public function __construct(SkillCategory $category, array $items)
    {
        parent::__construct($category);

        $this->skills = array_map(fn(Skill $s) => new ReferenceResponse($s), $items);
    }
}
