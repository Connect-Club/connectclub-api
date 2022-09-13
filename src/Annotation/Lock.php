<?php

namespace App\Annotation;

/** @Annotation  */
class Lock
{
    public string $code;
    public int $timeout = 300;
    public bool $personal = false;
}
