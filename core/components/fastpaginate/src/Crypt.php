<?php

namespace Boshnik\FastPaginate;

class Crypt
{
    public function __construct(private readonly string $key) {}

    public function encrypt($input): array|string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        if ($iv === false) {
            return ['error' => 'Error generating IV'];
        }

        $input = json_encode($input);

        $encryptedData = openssl_encrypt($input, 'aes-256-cbc', $this->key, 0, $iv);
        $encryptedPayload = base64_encode($encryptedData . '::' . $iv);

        return str_replace('/', '_', $encryptedPayload);
    }

    public function decrypt($input): array|string
    {
        $input = str_replace('_', '/', $input);
        $data = base64_decode($input);
        if ($data === false) {
            return ['error' => 'Error decoding base64 data'];
        }

        list($encryptedData, $iv) = explode('::', $data, 2);
        if ($iv === false || $encryptedData === false) {
            return ['error' => 'Error extracting IV or encrypted data'];
        }

        $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $this->key, 0, $iv);
        if ($decryptedData === false) {
            return ['error' => 'Error decrypting data'];
        }

        $output = json_decode($decryptedData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Error decoding JSON'];
        }

        return $output;
    }
}