<?php
namespace Worken\Services;

use Tighten\SolanaPhpSdk\Keypair;
use Worken\Utils\KeyFactory;
use Worken\Utils\Constants;
use GuzzleHttp\Client;

class WalletService {
    private $rpcClient;
    private $mintAddress;
    private $client;

    public function __construct($rpcClient) {
        $this->rpcClient = $rpcClient;
        $this->mintAddress = Constants::MINT_TOKEN;
        $this->client = new Client();
    }

    /**
     * Get balance of WORK token for a given wallet address
     * 
     * @param string $address
     * @return array Balance in lamports, SOL, and Hex value
     */
    public function getBalance(string $address) {
        try {
            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getTokenAccountsByOwner',
                    'params' => [
                        $address,
                        ["mint" => $this->mintAddress],
                        ["encoding" => "jsonParsed"]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if(isset($result['error'])) {
                return ['error' => $result['error']];
            }

            if (isset($result['result'])) {
                $value = $result['result']['value'][0] ?? null;
            
                if ($value && isset($value['account']['data']['parsed']['info']['tokenAmount'])) {
                    $amount = $value['account']['data']['parsed']['info']['tokenAmount']['amount'] ?? null;
                    $decimals = $value['account']['data']['parsed']['info']['tokenAmount']['decimals'] ?? null;
                    $uiAmount = $value['account']['data']['parsed']['info']['tokenAmount']['uiAmount'] ?? null;
                    $uiAmountString = $value['account']['data']['parsed']['info']['tokenAmount']['uiAmountString'] ?? null;
            
                    $tokenAmount = [
                        'amount' => $amount,
                        'decimals' => $decimals,
                        'uiAmount' => $uiAmount,
                        'uiAmountString' => $uiAmountString
                    ];
            
                    return $tokenAmount;
                } else {
                    return [
                        'amount' => '0',
                        'decimals' => 0,
                        'uiAmount' => 0,
                        'uiAmountString' => '0'
                    ];
                }
            } else {
                return [
                    'amount' => '0',
                    'decimals' => 0,
                    'uiAmount' => 0,
                    'uiAmountString' => '0'
                ];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get balance of SOL token for a given wallet address
     * 
     * @param string $address
     * @return array Balance in lamports, SOL, and Hex value
     */
    public function getSOLBalance(string $address) {
        try {
            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getBalance',
                    'params' => [
                        $address,
                        ["encoding" => "jsonParsed"]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if(isset($result['error'])) {
                return ['success' => false, 'data' => $result['error']];
            }

            if (isset($result['result'])) {
                $value = $result['result']['value'] ?? null;
                return ['success' => true, 'data' => $value];
            } else {
                return ['success' => true, 'data' => 0];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'data' => $e->getMessage()];
        }
    }

    /**
     * Get information about a Solana wallet
     * 
     * @param string $address
     * @return array
     */
    public function getInformation(string $address) {
        try {
            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getAccountInfo',
                    'params' => [
                        $address,
                        ["encoding" => "base58"]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if(isset($result['error'])) {
                return ['error' => $result['error']];
            }

            if (isset($result['result'])) {
                return $result['result']['value'] ?? null;
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a new SOL wallet, remember to store the seed phrase securely
     * 
     * @param int $words Number of words for the mnemonic (default 12 words, 24 words is also common) 
     * 
     * @return array Wallet information (seedphrase, private key, public key)
     */
    public function createWallet(int $words = 12) {
        $seed = KeyFactory::generateMnemonic($words);
        $keypair = Keypair::fromSeed($seed->entropy);
        return [
            'seedPhrase' => $seed->words,
            'privateKeyBase58' => $keypair->getSecretKey()->toBase58String(),
            'publicKey' => $keypair->getPublicKey()->toBase58(),
        ];
    }

    /**
     * Get history of transactions for a given wallet address
     * 
     * @param string $address
     * @return array
     */
    public function getHistory(string $address, int $limit = 10) {
        try {
            $client = new Client();
            // Fetching transaction signatures involving the wallet address
            $signatureResponse = $client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getSignaturesForAddress',
                    'params' => [
                        $address,
                        [
                            'limit' => $limit, // Adjust the limit as necessary
                            'options' => [
                                'commitment' => 'confirmed'
                            ]
                        ]
                    ]
                ]
            ]);
    
            $signatures = json_decode($signatureResponse->getBody()->getContents(), true);
            $transactions = [];
    
            if (isset($signatures['error'])) {
                return ['error' => $signatures['error']];
            }
            return $signatures['result'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}