<?php

namespace App\Describer;

use EXSyst\Component\Swagger\Swagger;
use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FixDefaultDescriber.
 *
 * @internal
 */
class FixDefaultDescriber implements DescriberInterface
{
    private RequestStack $requestStack;

    /**
     * FixDefaultDescriber constructor.
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function describe(Swagger $api)
    {
        if (!$request = $this->requestStack->getCurrentRequest()) {
            return;
        }

        /*
         * Fix nelmio api doc bundle bug if port != 80
         * Nelmio provide incorrect host for swagger ui (without port)
         * Is not working with docker for example
         */
        if (80 != $request->getPort()) {
            $api->setHost($request->getHost().':'.$request->getPort());
        }
    }
}
