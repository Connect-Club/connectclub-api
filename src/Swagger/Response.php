<?php

namespace App\Swagger;

class Response
{
    /** @var string */
    public $entityClass;

    /** @var string[] */
    public $groups;

    /** @var bool */
    public bool $security = true;

    public array $errorCodesMap = [];
}
