<?php

namespace Worken\Utils;

use FurqanSiddiqui\BIP39\BIP39;

class KeyFactory {
    /**
     * Generate seed phrase
     * 
     * @param int $words Number of words in mnemonic
     * @return object 
     */
    public static function generateMnemonic(int $words) {
        $response = new \stdClass();
        if (!in_array($words, [12, 24])) {
            throw new \InvalidArgumentException("Invalid number of words for a mnemonic. Allowed values are 12, 24.");
        }
        $mnemonic = BIP39::Generate($words);
        $response->words = $mnemonic->words; //Words in array
        $response->wordsString = implode(" ", $mnemonic->words); //Words in string imploded by space
        $response->entropy = $mnemonic->entropy; // Entropy of mnemonic, ready for generating private key
        return $response;
    }
}