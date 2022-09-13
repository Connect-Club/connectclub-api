<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class EntityExist extends Constraint
{
    public $message = '';

    public $entityClass;

    public $field;

    public $ignoreValues;
}
