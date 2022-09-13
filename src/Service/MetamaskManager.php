<?php

namespace App\Service;

use App\ConnectClub;
use App\Exception\User\MetamaskInvalidWalletDataException;
use Elliptic\EC;
use kornrunner\Keccak;

class MetamaskManager
{
    public function checkMetamaskWallet(string $expectedNonce, string $text, string $address, string $signature): bool
    {
        //phpcs:ignore
        if (
            ConnectClub::generateMetamaskMessageForNonce($expectedNonce) != $text &&
            //@todo remove after fix mobile app by Alexander (duplicate nonce)
            ConnectClub::generateMetamaskMessageForNonce($expectedNonce).$expectedNonce != $text
        ) {
            throw new MetamaskInvalidWalletDataException('invalid_text');
        }

        $messageLength = strlen($text);
        $hash = Keccak::hash("\x19Ethereum Signed Message:\n{$messageLength}{$text}", 256);
        $sign = [
            "r" => substr($signature, 2, 64),
            "s" => substr($signature, 66, 64)
        ];

        $recId  = ord(hex2bin(substr($signature, 130, 2))) - 27;
        if ($recId != ($recId & 1)) {
            throw new MetamaskInvalidWalletDataException('invalid_signature');
        }

        $publicKey = (new EC('secp256k1'))->recoverPubKey($hash, $sign, $recId);
        $addressEncoded = "0x" . substr(Keccak::hash(substr(hex2bin($publicKey->encode("hex")), 1), 256), 24);

        if ($addressEncoded !== mb_strtolower($address)) {
            throw new MetamaskInvalidWalletDataException('invalid_signature_address');
        }

        return true;
    }
}
