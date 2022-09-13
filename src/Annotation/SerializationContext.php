<?php

namespace App\Annotation;

use Doctrine\Common\Annotations\Annotation;

/** @Annotation */
class SerializationContext extends Annotation
{
    public bool $serializeAsObject = false;
}
