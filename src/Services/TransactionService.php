<?php
namespace Worken\Services;

use GuzzleHttp\Client;
use Worken\Utils\TokenProgram;
use Worken\Utils\Constants;

class TransactionService {
    private $rpcClient;
    private $mintAddress;
    private $client;

    public function __construct($rpcClient) {
        $this->rpcClient = $rpcClient;
        $this->mintAddress = Constants::MINT_TOKEN;
        $this->client = new Client();
    }

    /**
     * Prepare transaction in Worken SPL token
     *
     * @param string $sourcePrivateKey Sender private key in base58
     * @param string $sourceWallet Sender wallet address
     * @param string $destinationWallet Receiver wallet address
     * @param float $amount Amount to send in WORKEN
     * @return array
     */
    public function prepareTransaction(string $sourcePrivateKey, string $sourceWallet, string $destinationWallet, int $amount): array {
        try {
            $hashString = TokenProgram::prepareTransaction($sourcePrivateKey, $sourceWallet, $destinationWallet, $amount, $this->rpcClient);
            return ['success' => true, 'message' => 'Transaction prepared successfully', 'data' => $hashString];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error preparing transaction: ' . $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Prepare transaction with burn in Worken SPL token (possible to send SOL too)
     *
     * @param string $sourcePrivateKey Sender private key in base58
     * @param string $sourceWallet Sender wallet address
     * @param string $destinationWallet Receiver wallet address
     * @param float $sendAmount Amount to send in WORKEN
     * @param float $burnAmount Amount to burn in WORKEN
     * @param float $solAmount Amount to send in SOL (optional)
     * 
     * @return array
     */
    public function prepareTransactionWithBurn(string $sourcePrivateKey, string $sourceWallet, string $destinationWallet, int $sendAmount, int $burnAmount, int $solAmount = 0): array {
        try {
            $hashString = TokenProgram::prepareTransactionWithBurn($sourcePrivateKey, $sourceWallet, $destinationWallet, $sendAmount, $burnAmount, $this->rpcClient, $solAmount);
            return ['success' => true, 'message' => 'Transaction with burn prepared successfully', 'data' => $hashString];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error preparing transaction with burn: ' . $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Send prepared transaction
     *
     * @param array $hashString Prepared transaction hash
     * @return array
     */
    public function sendTransaction(array $hashString) {
        try {
            if ($hashString['success'] === false) {
                return ['success' => false, 'message' => $hashString['message'], 'data' => null];
            }
            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'sendTransaction',
                    'params' => [
                        $hashString['data'],
                        ['encoding' => 'base64']
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            if (isset($result['error'])) {
                if (isset($result['error']['data']['logs'])) {
                    foreach ($result['error']['data']['logs'] as $log) {
                        if (strpos($log, 'insufficient funds') !== false) {
                            return ['success' => false, 'message' => 'Transaction failed: insufficient WORK funds', 'data' => null];
                        }
                        if (strpos($log, 'insufficient lamports') !== false) {
                            return ['success' => false, 'message' => 'Transaction failed: insufficient SOL funds', 'data' => null];
                        }
                    }
                }
                return ['success' => false, 'message' => 'Transaction failed: ' . $result['error']['message'], 'data' => null];
            }
            $signature = $result['result'];
            return ['success' => true, 'message' => 'Transaction sent successfully', 'data' => $signature];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error sending transaction: ' . $e->getMessage(), 'data' => null];
        }
    }

    
    /**
     * Get estimated fee for the transaction
     * 
     * @param string $hashString prepared transaction hash
     * 
     */
    public function getEstimatedFee(string $hashString) {
        try {
            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getFeeForMessage',
                    'params' => [
                        $hashString,
                        ['encoding' => 'base64', "commitment" => "processed"]
                    ]
                ]
            ]);
    
            $result = json_decode($response->getBody()->getContents(), true);
            if (isset($result['error'])) {
                return ['error' => $result['error']];
            }

            $fee = $result['result']['value']['fee'];
            return $fee;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get transactions status
     * 
     * @param array $signature Transaction hashes in array
     * 
     * @return array
     */
    public function getTransactionStatus(array $signatures) {
        $response = $this->client->post($this->rpcClient, [
            'json' => [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => 'getSignatureStatuses',
                'params'  => [
                    $signatures,
                    ['searchTransactionHistory' => true]
                ]
            ]
        ]);
    
        $result = json_decode($response->getBody(), true);
        
        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']['message']];
        }
    
        $statuses = $result['result']['value'];
        $responseArray = [];
        $success = true;
    
        foreach ($statuses as $index => $transactionStatus) {
            $signature = $signatures[$index];
            if ($transactionStatus === null) {
                $responseArray[] = [
                    'signature' => $signature,
                    'finalized' => false,
                    'error' => true,
                    'message' => "No transaction found"
                ];
                $success = false;
                continue;
            }
    
            $finalized = $transactionStatus['confirmationStatus'] === 'finalized' || $transactionStatus['confirmationStatus'] === 'confirmed';
            $error = $transactionStatus['err'] !== null;
    
            $responseArray[] = [
                'signature' => $signature,
                'finalized' => $finalized,
                'error' => $error
            ];
        }
    
        return [
            'success' => $success,
            'data' => $responseArray
        ];
    }

    /** 
     * Get 10 recent transactions of the Worken SPL token
     * 
     * @return array
     */
    public function getRecentTransactions() {
        try {
            // Fetching transaction signatures involving the wallet address
            $signatureResponse = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getSignaturesForAddress',
                    'params' => [
                        $this->mintAddress,
                        [
                            'limit' => 10, // Adjust the limit as necessary
                        ]
                    ]
                ]
            ]);
    
            $signatures = json_decode($signatureResponse->getBody()->getContents(), true);
    
            if (isset($signatures['error'])) {
                return ['error' => $signatures['error']];
            }
            return $signatures['result'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}