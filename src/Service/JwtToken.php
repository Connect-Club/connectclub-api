<?php

namespace App\Service;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class JwtToken
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getJWTClaim(?string $token): ?string
    {
        if ($_ENV['APP_ENV'] == 'codeception') {
            return Uuid::uuid4()->toString();
        }

        if (!$token) {
            $this->logger->error('Jwt token is empty');
            return null;
        }

        try {
            $key = InMemory::plainText(file_get_contents(__DIR__.'/../../jwt-x509.pem'));
            $configuration = Configuration::forSymmetricSigner(new Sha256(), $key);

            $token = $configuration->parser()->parse($token);

            $validator = new Validator();
            if (!$validator->validate($token, new SignedWith(new Sha256(), $key))) {
                $this->logger->error('Jwt token not signed');
                return null;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('JWT token error '.$exception->getMessage().' token '.$token);
            return null;
        }

        return $token->claims()->get('jti');
    }
}
