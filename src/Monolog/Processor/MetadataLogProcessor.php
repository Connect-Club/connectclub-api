<?php

namespace App\Monolog\Processor;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MetadataLogProcessor
{
    private RequestStack $requestStack;
    private TokenStorageInterface $tokenStorage;

    /**
     * MetadataLogFormatter constructor.
     */
    public function __construct(RequestStack $requestStack, TokenStorageInterface $tokenStorage)
    {
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return array
     */
    public function __invoke(array $record)
    {
        if ($request = $this->requestStack->getCurrentRequest()) {
            $record['context']['Request-ID'] = $request->attributes->get('request_id');
        }

        if ($token = $this->tokenStorage->getToken()) {
            if ($user = $token->getUser()) {
                $record['context']['User-ID'] = $user instanceof User ? $user->getId() : $user;
            }
        }

        return $record;
    }
}
