<?php
namespace Worken\Services;

use GuzzleHttp\Client;

class NetworkService {
    private $rpcClient;
    private $client;

    public function __construct($rpcClient) {
        $this->rpcClient = $rpcClient;
        $this->client = new Client();
    }

    /**
     * Get block information
     * 
     * @param string $blockNumber block number 
     * @return array
     */
    public function getBlockInformation(int $blockNumber) {
        try {
            $client = new Client();
            $response = $client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getBlock',
                    'params' => [
                        $blockNumber,
                        [
                            "encoding" => "jsonParsed",
                            "transactionDetails" => "none",
                            "maxSupportedTransactionVersion" => 0,
                            "rewards" => false
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['result'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get network status information (block height, fee rate)
     * 
     * @return array
     */
    public function getNetworkStatus() {
        try {
            $status = [];

            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getBlockHeight'
                ]
            ]);
            $blockData = json_decode($response->getBody(), true);
            if (isset($blockData['result'])) {
                $status['latestBlock'] = $blockData['result'];
            } else {
                $status['latestBlock'] = 'Error fetching block height';
            }

            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getFeeCalculatorForBlockhash',
                    'params' => ["recent", ["commitment" => "finalized"]]
                ]
            ]);
            $feeData = json_decode($response->getBody(), true);
            if (isset($feeData['result']['value']['feeCalculator'])) {
                $status['feeRateLamportsPerSignature'] = $feeData['result']['value']['feeCalculator']['lamportsPerSignature'];
            } else {
                $status['feeRateLamportsPerSignature'] = 'Error fetching fee calculator';
            }

            return $status;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get congestion status of the network
     * 
     * @return array
     */
    public function getMonitorCongestion() {
        try {
            $status = [];

            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getRecentPerformanceSamples',
                    'params' => [5]  
                ]
            ]);
            $congestionData = json_decode($response->getBody(), true);
            if (isset($congestionData['result'])) {
                $status['performanceSamples'] = $congestionData['result'];
            } else {
                $status['performanceSamples'] = 'Error fetching performance data';
            }

            return $status;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}