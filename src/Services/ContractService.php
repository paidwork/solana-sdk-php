<?php
namespace Worken\Services;

use GuzzleHttp\Client;
use Worken\Utils\Constants;

class ContractService
{
    private $rpcClient;
    private $mintAddress;
    private $client;

    public function __construct($rpcClient)
    {
        $this->rpcClient = $rpcClient;
        $this->mintAddress = Constants::MINT_TOKEN;
        $this->client = new Client();
    }

    /**
     * Get contract status
     *
     * @return array
     */
    public function getContractStatus()
    {
        try {
            $result = [];

            $response = $this->client->post($this->rpcClient, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getAccountInfo',
                    'params' => [
                        $this->mintAddress,
                        ['encoding' => 'jsonParsed']
                    ]
                ]
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (isset($responseData['result'])) {
                $result = true;
            } else {
                $result = false;
            }

            return $result;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get contract program data - to do
     *
     * @return string
     */
    // public function getContractFunction()
    // {
    //     try {
    //         $abi = "";

    //         $client = new Client();
    //         $response = $client->post($this->rpcClient, [
    //             'json' => [
    //                 'jsonrpc' => '2.0',
    //                 'id' => 1,
    //                 'method' => 'getAccountInfo',
    //                 'params' => [
    //                     $this->mintAddress,
    //                     ['encoding' => 'base64']
    //                 ]
    //             ]
    //         ]);

    //         $responseData = json_decode($response->getBody(), true);

    //         if (isset($responseData['result']['value']['data'][0])) {
    //             $abi = base64_decode($responseData['result']['value']['data'][0]);
    //         }

    //         return $abi;
    //     } catch (\Exception $e) {
    //         return ['error' => $e->getMessage()];
    //     }
    // }
}