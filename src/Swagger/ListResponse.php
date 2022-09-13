<?php

namespace App\Swagger;

/**
 * Class ListResponse.
 *
 * @Annotation
 */
class ListResponse extends Response
{
    public bool $pagination = false;
    public bool $paginationByLastValue = false;
    public bool $paginationWithTotalCount = false;
    public bool $enableOrderBy = true;
}
