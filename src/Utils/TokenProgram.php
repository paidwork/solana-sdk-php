<?php

namespace Worken\Utils;

use Worken\Utils\Instructions;
use Worken\Utils\Constants;
use Tighten\SolanaPhpSdk\PublicKey;
use Tighten\SolanaPhpSdk\TransactionInstruction;
use Tighten\SolanaPhpSdk\Keypair;
use Tighten\SolanaPhpSdk\Transaction;
use Tighten\SolanaPhpSdk\Util\AccountMeta;
use StephenHill\Base58;
use GuzzleHttp\Client;

class TokenProgram
{
    /**
     * Prepare transaction
     * 
     * @param string $sourcePrivateKey Sender private key in base58
     * @param string $destinationWallet Receiver wallet address
     * @param int $amount Amount to send in WORKEN
     * @param string $rpcClient RPC client
     * @param string $mintAddress Mint address
     * 
     * @return string Transaction hash
     */
    public static function prepareTransaction(
        string $sourcePrivateKey, 
        string $senderWallet, 
        string $destinationWallet, 
        int $amount, 
        string $rpcClient
        ): string {
        $fromBase58 = PublicKey::base58()->decode($sourcePrivateKey);
        $senderKeyPair = Keypair::fromSecretKey($fromBase58);
        $senderPubKey = new PublicKey($senderWallet);
        $receiverPubKey = new PublicKey($destinationWallet);
        
        $sourceAccount = TokenProgram::getOrCreateAssociatedTokenAccount(
            $senderKeyPair, $senderPubKey, $senderPubKey, $rpcClient
        );

        $destinationAccount = TokenProgram::getOrCreateAssociatedTokenAccount(
            $senderKeyPair, $senderPubKey, $receiverPubKey, $rpcClient
        );

        //$numberDecimals = Constants::MINT_DECIMALS; // Decimals of SPL token
        // $tokenAmount = $amount * pow(10, $numberDecimals);

        $instruction = Instructions::transferSPL(
            $sourceAccount,
            $destinationAccount,
            $senderPubKey,
            $amount
        );

        $recentBlockhash = TokenProgram::getRecentBlockhash($rpcClient);
        $transaction = new Transaction($recentBlockhash, null, $senderPubKey);
        $transaction->add(Instructions::setComputeUnitLimit(462000));
        $transaction->add(Instructions::setComputeUnitPrice(300000));
        if($destinationAccount instanceof TransactionInstruction) {
            $transaction->add($destinationAccount);
            $destinationAccount = self::findAssociatedTokenAddress($receiverPubKey, new PublicKey(Constants::MINT_TOKEN));
        }
        $transaction->add($instruction);

        $transaction->sign($senderKeyPair); 
        $rawBinaryString = $transaction->serialize(false);
        $hashString = sodium_bin2base64($rawBinaryString, SODIUM_BASE64_VARIANT_ORIGINAL);

        return $hashString;
    }

    /**
     * Prepare transaction with burning WORKEN on the sender's wallet
     * 
     * @param string $senderPrivateKey Sender private key in base58
     * @param string $burnerWallet Receiver wallet address
     * @param int $amount Amount to send in WORKEN
     * @param string $rpcClient RPC client
     * @param string $mintAddress Mint address
     * 
     * @return string Transaction hash
     */
    public static function prepareTransactionWithBurn(
        string $senderPrivateKey, 
        string $senderWallet,
        string $destinationWallet, 
        int $sendAmount,
        int $burnAmount,
        string $rpcClient,
        int $solAmount
    ): string 
    {
    $senderfromBase58 = PublicKey::base58()->decode($senderPrivateKey);
    $senderKeyPair = Keypair::fromSecretKey($senderfromBase58);
    $senderPubKey = new PublicKey($senderWallet);

    $receiverPubKey = new PublicKey($destinationWallet);

    $sourceAccount = TokenProgram::getOrCreateAssociatedTokenAccount(
        $senderKeyPair, $senderPubKey, $senderPubKey, $rpcClient
    );

    $destinationAccount = TokenProgram::getOrCreateAssociatedTokenAccount(
        $senderKeyPair, $senderPubKey, $receiverPubKey, $rpcClient
    );
    
    // $numberDecimals = Constants::MINT_DECIMALS; // Decimals of SPL token
    // $tokenAmount = $amount * pow(10, $numberDecimals);

    $burnInstruction = Instructions::burnSPL(
        $sourceAccount,
        $senderPubKey,
        $burnAmount
    );

    $recentBlockhash = TokenProgram::getRecentBlockhash($rpcClient);
    $transaction = new Transaction($recentBlockhash, null, $senderPubKey);
    $transaction->add(Instructions::setComputeUnitLimit(462000));
    $transaction->add(Instructions::setComputeUnitPrice(300000));
    if($destinationAccount instanceof TransactionInstruction) {
        $transaction->add($destinationAccount);
        $destinationAccount = self::findAssociatedTokenAddress($receiverPubKey, new PublicKey(Constants::MINT_TOKEN));
    }
    $transferInstruction = Instructions::transferSPL(
        $sourceAccount,
        $destinationAccount,
        $senderPubKey,
        $sendAmount
    );
    $transaction->add($transferInstruction);
    $transaction->add($burnInstruction);

    //TO DO - Add SOL transfer instruction if $solAmount > 0
    if($solAmount > 0) {
        $minimumRent = TokenProgram::getMinimumBalanceForRentExemption($rpcClient);
        if ($solAmount < $minimumRent) {
            throw new \Exception("SOL Amount is less than minimum rent.");
        }
        $solTransferInstruction = Instructions::transferSOL(
            $senderPubKey,
            $receiverPubKey,
            $solAmount
        );
        $transaction->add($solTransferInstruction);
    }

    $transaction->sign($senderKeyPair); 
    $rawBinaryString = $transaction->serialize(false);
    $hashString = sodium_bin2base64($rawBinaryString, SODIUM_BASE64_VARIANT_ORIGINAL);

    return $hashString;
}

    public static function getNumberDecimals(PublicKey $mintAddress, $rpc) {
        $client = new Client();
        $response = $client->post($rpc, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getParsedAccountInfo',
                'params' => [$mintAddress->toBase58()]
            ]
        ]);
    
        $result = json_decode($response->getBody(), true);
        if (isset($result['result']['value']['data']['parsed']['info']['decimals'])) {
            return $result['result']['value']['data']['parsed']['info']['decimals'];
        } else {
            throw new \Exception("Failed to fetch token decimals.");
        }
    }

    /**
     * Fetches or creates an associated token account for a given mint and owner.
     * 
     * @param Keypair $ownerKeys The keypair of the account that will own the newly created token account.
     * @param PublicKey $mintPublicKey The public key of the token for which an account will be created.
     * @param PublicKey $accountPublicKey The public key of the account that will own the newly created token account.
     * @param string $rpc The RPC endpoint URL
     * @return PublicKey The public key of the associated token account
     */
    public static function getOrCreateAssociatedTokenAccount(Keypair $ownerKeys, PublicKey $senderPublicKey, PublicKey $accountPublicKey, $rpc)
    {
        $client = new Client();
        $mintPublicKey = new PublicKey(Constants::MINT_TOKEN);
        // Fetch existing associated token accounts
        $response = $client->post($rpc, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getTokenAccountsByOwner',
                'params' => [
                    $accountPublicKey->toBase58(),
                    ['mint' => $mintPublicKey->toBase58()],
                    ['encoding' => 'jsonParsed']
                ]
            ]
        ]);
    
        $result = json_decode($response->getBody(), true);
        if (isset($result['error'])) {
            throw new \Exception("Failed to fetch token accounts by owner: " . $result['error']['message']);
        }
    
        // Check if there are any associated accounts
        $associatedAccounts = $result['result']['value'];
        if (!empty($associatedAccounts)) {
            // Assuming we get the first associated account if multiple
            $associatedAccountAddress = $associatedAccounts[0]['pubkey'];
            return new PublicKey($associatedAccountAddress);
        }
    
        // No associated account exists, create a new one
        $instruction = self::createAssociatedTokenAccountInstruction(
            $senderPublicKey, // Sender is the fee payer
            $accountPublicKey, 
            $mintPublicKey,
        );
    
        // $recentBlockhash = self::getRecentBlockhash($rpc);
    
        // // Create and sign transaction
        // $transaction = new Transaction($recentBlockhash, null, $senderPublicKey); // Sender is the fee payer
        // $transaction->add(Instructions::setComputeUnitLimit(462000));
        // $transaction->add(Instructions::setComputeUnitPrice(300000));
        // $transaction->add($instruction);
    
        // $transaction->sign($ownerKeys);
    
        // // Send transaction
        // $serializedTransaction = sodium_bin2base64($transaction->serialize(), SODIUM_BASE64_VARIANT_ORIGINAL);
        // $response = $client->post($rpc, [
        //     'json' => [
        //         'jsonrpc' => '2.0',
        //         'id' => 1,
        //         'method' => 'sendTransaction',
        //         'params' => [
        //             $serializedTransaction,
        //             ['encoding' => 'base64']
        //         ]
        //     ]
        // ]);
    
        // $sendResult = json_decode($response->getBody(), true);
        // if (isset($sendResult['error'])) {
        //     throw new \Exception("Failed to send transaction: " . $sendResult['error']['message']);
        // }
    
        // Assuming transaction is confirmed and the new ATA address can be calculated or fetched
        // $newAssociatedAccountAddress = self::findAssociatedTokenAddress($accountPublicKey, $mintPublicKey); 
        return $instruction;
    }

    /**
     * Creates an instruction to create an associated token account.
     *
     * @param PublicKey $funderPublicKey The public key of the account funding the creation.
     * @param PublicKey $ownerPublicKey The public key of the account that will own the newly created token account.
     * @param PublicKey $mintPublicKey The public key of the token for which an account will be created.
     * @return TransactionInstruction
     */
    public static function createAssociatedTokenAccountInstruction(
        PublicKey $funderPublicKey,
        PublicKey $ownerPublicKey,
        PublicKey $mintPublicKey
    ): TransactionInstruction {
        $associatedTokenAccountPublicKey = self::findAssociatedTokenAddress($ownerPublicKey, $mintPublicKey);
    
        $keys = [
            new AccountMeta($funderPublicKey, true, true), // Funder account, is signer and writable
            new AccountMeta($associatedTokenAccountPublicKey, false, true), // Associated Token Account, not signer, is writable
            new AccountMeta($ownerPublicKey, false, false), // Owner account, not signer, not writable
            new AccountMeta($mintPublicKey, false, false), // Mint account, not signer, not writable
            new AccountMeta(new PublicKey(Constants::SYSTEM_PROGRAM_ID), false, false), // System Program, not signer, not writable
            new AccountMeta(new PublicKey(Constants::TOKEN_PROGRAM_ID), false, false), // Token Program, not signer, not writable
            new AccountMeta(new PublicKey(Constants::ASSOCIATED_TOKEN_PROGRAM_ID), false, false), // Associated Token Program, not signer, not writable
        ];
    
        // Opcode for 'Create Associated Token Account' is usually 1
        $data = pack('C', 1);
    
        return new TransactionInstruction(
            new PublicKey(Constants::ASSOCIATED_TOKEN_PROGRAM_ID), 
            $keys,
            $data
        );
    }

    /**
     * Finds the address of the associated token account for a given mint and owner.
     *
     * @param PublicKey $ownerPublicKey
     * @param PublicKey $mintPublicKey
     * @return PublicKey
     */
    public static function findAssociatedTokenAddress(PublicKey $ownerPublicKey, PublicKey $mintPublicKey): PublicKey {
        $base58 = new Base58();
        $binaryKey = new PublicKey($base58->decode(Constants::TOKEN_PROGRAM_ID));
        
        return PublicKey::findProgramAddress([
            $ownerPublicKey->toBytes(),
            $binaryKey->toBytes(),
            $mintPublicKey->toBytes()
        ], new PublicKey(Constants::ASSOCIATED_TOKEN_PROGRAM_ID))[0];
    }

    /**
     * Fetches the recent blockhash from the RPC endpoint.
     * 
     * @param string $rpc The RPC endpoint URL
     * @return string The recent blockhash
     */
    public static function getRecentBlockhash($rpc) {
        $client = new Client();
        $response = $client->post($rpc, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getRecentBlockhash'
            ]
        ]);
    
        $blockhashResponse = json_decode($response->getBody(), true);
        if (!isset($blockhashResponse['error']) && isset($blockhashResponse['result']['value']['blockhash'])) {
            return $blockhashResponse['result']['value']['blockhash'];
        } else {
            throw new \Exception("Failed to fetch recent blockhash.");
        }
    }

    public static function getMinimumBalanceForRentExemption($rpc) {
        $client = new Client();
        $response = $client->post($rpc, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getMinimumBalanceForRentExemption',
                "params" => [50]
            ]
        ]);
    
        $minimumRent = json_decode($response->getBody(), true);
        
        if (!isset($minimumRent['error']) && isset($minimumRent['result'])) {
            return $minimumRent['result'];
        } else {
            throw new \Exception("Failed to fetch minimum balance for rent excemption.");
        }
    }
}
