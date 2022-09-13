<?php

namespace App\Swagger;

use Symfony\Component\PropertyInfo\Type;

class Model
{
    private Type $type;
    private ?array $groups;
    private ?array $options;

    public function __construct(Type $type, array $groups = null, array $options = null)
    {
        $this->type = $type;
        $this->groups = $groups;
        $this->options = $options;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    /**
     * @return string[]|null
     */
    public function getGroups(): ?array
    {
        return $this->groups;
    }

    public function getHash(): string
    {
        return md5(serialize([$this->type, $this->groups]));
    }

    /**
     * @return mixed[]|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }
}
