<?php

namespace App\Service\SmartContract;

use App\Entity\Ethereum\Token;
use Ethereum\DataType\EthD;

class SmartContractERC721 extends AbstractSmartContract
{
    public function getBalance(Token $token, string $wallet): int
    {
        return $this->getSmartContract($token)->balanceOf(//@phpstan-ignore-line
            new EthD($wallet)
        )->val();
    }
}
