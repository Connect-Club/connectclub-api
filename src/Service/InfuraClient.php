<?php

namespace App\Service;

use App\Entity\Ethereum\Token;
use App\Service\SmartContract\AbstractSmartContract;
use App\Service\SmartContract\SmartContractERC1155;
use App\Service\SmartContract\SmartContractERC721;
use Ethereum\Ethereum;
use Ethereum\SmartContract;
use RuntimeException;

class InfuraClient
{
    public function getSmartContractClient(Token $token = null): SmartContract
    {
        if ($token && $token->abi && !$token->isInternal) {
            $contractMetaAbi = json_decode(json_encode($token->abi));
            $infuraURL = 'https://polygon-mainnet.infura.io/v3/'.$_ENV['ETHEREUM_INFURA_KEY'];
        } else {
            $infuraURL = 'https://'.$_ENV['ETHEREUM_NETWORK_NAME'].'.infura.io/v3/'.$_ENV['ETHEREUM_INFURA_KEY'];
            $contractMetaAbi = json_decode(
                file_get_contents(__DIR__.'/../../var/'.$_ENV['ETHEREUM_CONTACT_FILE_NAME'])
            )->abi;
        }

        return new SmartContract($contractMetaAbi, $token->contractAddress, new Ethereum($infuraURL));
    }

    public function getSmartContract(Token $token): AbstractSmartContract
    {
        switch ($token->contractType) {
            case 'erc-1155':
                return new SmartContractERC1155();
            case 'erc-721':
                return new SmartContractERC721();
            default:
                throw new RuntimeException('No smart contract client for '.$token->contractType);
        }
    }
}
