<?php

include './vendor/autoload.php';

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

$k = InMemory::file(__DIR__ . '/jwt.pem');
$c = Configuration::forSymmetricSigner(new Sha256(), $k);

$t = $c->parser()->parse('eyAiYWxnIjogIlJTMjU2IiwgInR5cCI6ICJKV1QiIH0.eyAianRpIjogIjU5YTI2NzhmLTg2MDgtNDNjMS1hMjk2LTQyYmVjMTM4ZmNkZCIsICJpYXQiOiAxNjU2NjA4MTg0LCAib3MiOiAiYW5kcm9pZCIgfQ.h-A6pPnEOZMwjoQExbOeSZG9-MtrVM5Nj63ZITav4ZwXVN0nSaPUK1F-RFm5HYqsmO0caQ6MRNtXB6LbQloGN0RvrKaTU69QB4TkJnZxUxqV4Ckak1CTsSYqAYyC2bcVwFJ4lZIVXdLK0SQtkJMxXhulJaSCA7wlOhQMiS_v2fd2zX7PmVi9MWpJnyCZgNm3O_KaV2HBTV5Re6KtWrk8ZTJq9-mcCb-Wqdher1_ug001ikdYbOUx6sIQFgSZgR1bWKr6Oxotp-uOU9AEBq8C2-JuHpoOjUya7-qdToBi7hIauRFvrX0xqQzja-EObVVK0jnaEgsYEzEna83kPq4cyA');

dump($t->claims()->get('jti'));