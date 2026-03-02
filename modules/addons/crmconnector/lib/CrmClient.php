<?php

namespace WHMCS\Module\Addon\CrmConnector;

class CrmClient
{
    private $endpoint;

    private $apiKey;

    public function __construct($endpoint, $apiKey)
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
    }

    public function upsertContact(array $payload)
    {
        if (empty($this->endpoint)) {
            return [
                'success' => true,
                'external_id' => 'mock-' . $payload['userid'],
                'message' => 'Endpoint not configured. Mock sync recorded.',
            ];
        }

        $url = $this->endpoint . '/contacts/upsert';
        $body = json_encode($payload);

        if ($body === false) {
            return [
                'success' => false,
                'message' => 'JSON encoding failed.',
            ];
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $responseBody = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error,
            ];
        }

        $decoded = json_decode((string) $responseBody, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'external_id' => $decoded['id'] ?? ('ext-' . $payload['userid']),
                'message' => 'Synced',
            ];
        }

        return [
            'success' => false,
            'message' => $decoded['message'] ?? ('Unexpected response code ' . $httpCode),
        ];
    }
}
