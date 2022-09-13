<?php

namespace App\Service\SmartContract;

use App\Entity\Ethereum\Token;
use Ethereum\Ethereum;
use Ethereum\SmartContract;

abstract class AbstractSmartContract
{
    abstract public function getBalance(Token $token, string $wallet): int;

    public function getSmartContract(Token $token): SmartContract
    {
        if ($token->abi && !$token->isInternal) {
            $contractMetaAbi = json_decode(json_encode($token->abi));
            $infuraURL = 'https://polygon-mainnet.infura.io/v3/'.$_ENV['ETHEREUM_INFURA_KEY'];
        } else {
            $infuraURL = 'https://'.$_ENV['ETHEREUM_NETWORK_NAME'].'.infura.io/v3/'.$_ENV['ETHEREUM_INFURA_KEY'];
            $contractMetaAbi = json_decode(
                file_get_contents(__DIR__.'/../../../var/'.$_ENV['ETHEREUM_CONTACT_FILE_NAME'])
            )->abi;
        }

        return new SmartContract($contractMetaAbi, $token->contractAddress, new Ethereum($infuraURL));
    }
}
