<?php

namespace Worken;

use Worken\Services\WalletService;
use Worken\Services\TransactionService;
use Worken\Services\ContractService;
use Worken\Services\NetworkService;
use Tighten\SolanaPhpSdk\SolanaRpcClient;

class Worken {
    public $wallet;
    public $transaction;
    public $contract;
    public $network;

    const LOCAL_ENDPOINT = 'http://localhost:8899';
    const DEVNET_ENDPOINT = 'https://api.devnet.solana.com';
    const TESTNET_ENDPOINT = 'https://api.testnet.solana.com';
    const MAINNET_ENDPOINT = 'https://api.mainnet-beta.solana.com';

    /**
     * Worken-SDK constructor
     */
    public function __construct($rpcChoice) {
        $nodeUrl = $this->resolveRpcUrl($rpcChoice);

        $this->wallet = new WalletService($nodeUrl);
        $this->contract = new ContractService($nodeUrl);
        $this->network = new NetworkService($nodeUrl);
        $this->transaction = new TransactionService($nodeUrl);
    }

    private function resolveRpcUrl($choice) {
        switch ($choice) {
            case 'MAINNET':
                return self::MAINNET_ENDPOINT;
            case 'TESTNET':
                return self::TESTNET_ENDPOINT;
            case 'DEVNET':
                return self::DEVNET_ENDPOINT;
            case 'LOCALNET':
                return self::LOCAL_ENDPOINT;
            default:
                return $choice;
        }
    }
}