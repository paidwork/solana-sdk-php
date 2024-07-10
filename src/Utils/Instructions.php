<?php

namespace Worken\Utils;

use Tighten\SolanaPhpSdk\PublicKey;
use Tighten\SolanaPhpSdk\TransactionInstruction;
use Tighten\SolanaPhpSdk\Util\AccountMeta;
use Worken\Enums\InstructionTypes;
use Worken\Utils\Constants;

class Instructions {

    /**
     * Create transfer instruction for SPL token
     * 
     * @param PublicKey $fromPubkey
     * @param PublicKey $toPublicKey
     * @param PublicKey $ownerPublicKey
     * @param int $tokenAmount
     * 
     * @return TransactionInstruction
     */
    public static function transferSPL(
        PublicKey $fromPubkey,
        PublicKey $toPublicKey,
        PublicKey $ownerPublicKey,
        int $tokenAmount
    ): TransactionInstruction {
        $amount = pack('P', $tokenAmount);  // 'P' for 64-bit unsigned integer, little endian
        $data = pack('C', InstructionTypes::Transfer) . $amount; // No need for decimals in the data for a transfer instruction

        $keys = [
            new AccountMeta($fromPubkey, false, true),
            new AccountMeta($toPublicKey, false, true),
            new AccountMeta($ownerPublicKey, true, false)
        ];

        return new TransactionInstruction(
            new PublicKey(Constants::TOKEN_PROGRAM_ID),
            $keys,
            $data
        );
    }

    /**
     * Create instruction for burning SPL token
     * 
     * @param PublicKey $accountPubkey
     * @param PublicKey $ownerPubkey
     * @param int $tokenAmount
     * 
     * @return TransactionInstruction
     */
    public static function burnSPL(
        PublicKey $accountPubkey,
        PublicKey $ownerPubkey,
        int $tokenAmount
    ): TransactionInstruction {
        $amount = pack('P', $tokenAmount);  
        $data = pack('C', InstructionTypes::Burn) . $amount;
        $mint = new PublicKey(Constants::MINT_TOKEN);

        $keys = [
            new AccountMeta($accountPubkey, false, true),
            new AccountMeta($mint, false, true),
            new AccountMeta($ownerPubkey, true, false)
        ];

        return new TransactionInstruction(
            new PublicKey(Constants::TOKEN_PROGRAM_ID),
            $keys,
            $data
        );
    }

    /**
     * Create transfer instruction for native SOL
     * 
     * @param PublicKey $fromPubkey Source wallet public key
     * @param PublicKey $toPublicKey Destination wallet public key
     * @param int $lamports Amount in lamports (1 SOL = 1,000,000,000 lamports)
     * 
     * @return TransactionInstruction
     */
    public static function transferSOL(
        PublicKey $fromPubkey,
        PublicKey $toPublicKey,
        int $lamports
    ): TransactionInstruction {
        $data = [
            ...unpack("C*", pack("V", 2)),
            ...unpack("C*", pack("P", $lamports)),
        ];
        $keys = [
            new AccountMeta($fromPubkey, true, true),
            new AccountMeta($toPublicKey, false, true),
        ];

        return new TransactionInstruction(
            new PublicKey(Constants::SYSTEM_PROGRAM_ID),
            $keys,
            $data
        );
    }

    /**
     * Create instruction for setting compute unit limit
     * 
     * @param int $lamports Amount in lamports (1 SOL = 1,000,000,000 lamports)
     * 
     * @return TransactionInstruction
     */
    public static function setComputeUnitLimit(int $lamports) {
        return new TransactionInstruction(
            new PublicKey(Constants::COMPUTE_BUDGET_PROGRAM),
            [], 
            pack('C', 0x02) . pack('V', $lamports) 
        );
    }

    /**
     * Create instruction for setting compute unit price
     * 
     * @param int $lamports Amount in lamports (1 SOL = 1,000,000,000 lamports)
     * 
     * @return TransactionInstruction
     */
    public static function setComputeUnitPrice(int $lamports) {
        return new TransactionInstruction(
            new PublicKey(Constants::COMPUTE_BUDGET_PROGRAM),
            [], 
            pack('C', 0x03) . pack('P', $lamports)  
        );
    }
}