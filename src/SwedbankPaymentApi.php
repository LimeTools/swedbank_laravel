<?php

namespace LimeTools\Swedbank;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SwedbankPaymentApi
{
    protected bool $isSandbox;

    public function __construct(bool $isSandbox = null)
    {
        $this->isSandbox = $isSandbox ?? (Config::get('swedbank.environment') === 'sandbox');
    }

    /**
     * Get the appropriate base URL based on environment
     */
    protected function getBaseUrl(): string
    {
        $environment = $this->isSandbox ? 'sandbox' : 'production';
        return Config::get("swedbank.endpoints.{$environment}.base_url");
    }

    /**
     * Generate JWS (JSON Web Signature) for authentication
     * 
     * @param array|string $payload The request payload
     * @param string $url The full URL for the request
     * @param string $clientId The client ID
     * @param string $privateKey The private key for signing
     * @return string The JWS signature in detached format
     */
    protected function generateJWS(array|string $payload, string $url, string $clientId, string $privateKey): string
    {
        // Create JWT header
        $header = [
            'b64' => false,
            'crit' => ['b64'],
            'iat' => time(),
            'alg' => 'RS512',
            'url' => $url,
            'kid' => 'LT:' . $clientId
        ];

        // Encode header
        $headerEncoded = $this->base64UrlEncode(json_encode($header));

        // Prepare payload
        if (!empty($payload) && is_array($payload)) {
            $payload = json_encode($payload);
        }

        // For detached JWS, sign the header + payload
        $signature = '';
        $privateKey = trim($privateKey);
        $privateKeyResource = openssl_pkey_get_private($privateKey);

        if (!$privateKeyResource) {
            throw new \Exception('Invalid private key provided');
        }

        $dataToSign = $headerEncoded . '.' . $payload;

        if (!openssl_sign($dataToSign, $signature, $privateKeyResource, OPENSSL_ALGO_SHA512)) {
            throw new \Exception('Failed to sign JWS');
        }

        $signatureEncoded = $this->base64UrlEncode($signature);

        // Return detached JWS format: <header>..<signature>
        return $headerEncoded . '..' . $signatureEncoded;
    }

    /**
     * Base64 URL encoding (RFC 4648)
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Create payment initiation (V3 API)
     * 
     * @param array $paymentData Payment data according to Swedbank V3 API specification
     * @param string $clientId Client ID
     * @param string $privateKey Private key for signing
     * @return string Authorization URL for user to complete payment
     * @throws \Exception
     */
    public function createPaymentInitiation(array $paymentData, string $clientId, string $privateKey): string
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/v3/transactions/providers/' . $paymentData['creditor']['bic'];

        $jws = $this->generateJWS($paymentData, $url, $clientId, $privateKey);

        $response = Http::withHeaders([
            'x-jws-signature' => $jws,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Request-ID' => uniqid('req_', true)
        ])->post($url, $paymentData);

        if (!$response->successful()) {
            $this->logError('Swedbank payment initiation failed', [
                'response' => $response->body(),
                'status' => $response->status(),
                'payment_data' => $paymentData,
                'url' => $url,
            ]);
            throw new \Exception('Failed to create payment initiation: ' . $response->body());
        }

        $paymentResponse = $response->json();

        // Return the authorization URL for user to complete payment
        return $paymentResponse['_links']['scaRedirect']['href'] ?? '';
    }

    /**
     * Get payment status (V3 API)
     * 
     * @param string $statusUrl The status URL returned from payment initiation
     * @param string $clientId Client ID
     * @param string $privateKey Private key for signing
     * @return array Payment status response
     * @throws \Exception
     */
    public function getPaymentStatus(string $statusUrl, string $clientId, string $privateKey): array
    {
        $jws = $this->generateJWS('', $statusUrl, $clientId, $privateKey);

        $response = Http::withHeaders([
            'x-jws-signature' => $jws,
            'Accept' => 'application/json',
            'X-Request-ID' => uniqid('req_', true)
        ])->get($statusUrl);

        if (!$response->successful()) {
            $this->logError('Swedbank payment status request failed', [
                'url' => $statusUrl,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);
            throw new \Exception('Failed to get payment status: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get payment providers list (V3 API)
     * 
     * @param string $country Country code (e.g., 'LT', 'LV', 'EE')
     * @param string $clientId Client ID
     * @param string $privateKey Private key for signing
     * @return array List of payment providers
     * @throws \Exception
     */
    public function getPaymentProviders(string $country, string $clientId, string $privateKey): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/v3/agreement/providers';

        $jws = $this->generateJWS('', $url, $clientId, $privateKey);

        $response = Http::withHeaders([
            'x-jws-signature' => $jws,
            'Accept' => 'application/json',
        ])->get($url);

        if (!$response->successful()) {
            $this->logError('Swedbank providers request failed', [
                'country' => $country,
                'response' => $response->body(),
                'status' => $response->status(),
                'url' => $url,
            ]);
            throw new \Exception('Failed to get payment providers: ' . $response->body());
        }

        $providers = $response->json();

        // Format providers response
        $formattedProviders = collect($providers ?? [])->map(function ($provider, $key) {
            return [
                'id' => $key,
                'name' => $provider['names']['shortNames']['lt'] ?? $provider['names']['shortNames']['en'] ?? '',
                'country' => $provider['country'] ?? '',
                'bic' => $provider['bic'] ?? '',
                'logo' => $provider['urls']['logo'] ?? '',
                'url' => $provider['urls']['payment'] ?? '',
            ];
        })->toArray();

        return $formattedProviders;
    }

    /**
     * Get payment initiation form data for specific provider (V3 API)
     * 
     * @param string $bic Bank Identifier Code
     * @param array $paymentData Payment data according to Swedbank V3 API specification
     * @param string $clientId Client ID
     * @param string $privateKey Private key for signing
     * @return array Payment initiation form response
     * @throws \Exception
     */
    public function getPaymentInitiationForm(string $bic, array $paymentData, string $clientId, string $privateKey): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/v3/transactions/providers/' . $bic;
        $jws = $this->generateJWS($paymentData, $url, $clientId, $privateKey);

        $response = Http::withHeaders([
            'x-jws-signature' => $jws,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Request-ID' => uniqid('req_', true)
        ])->post($url, $paymentData);

        if (!$response->successful()) {
            $this->logError('Swedbank payment initiation form failed', [
                'bic' => $bic,
                'response' => $response->body(),
                'status' => $response->status()
            ]);
            throw new \Exception('Failed to get payment initiation form: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Log error if logging is enabled
     */
    protected function logError(string $message, array $context = []): void
    {
        if (Config::get('swedbank.logging.enabled', true)) {
            Log::channel(Config::get('swedbank.logging.channel', 'swedbank'))
                ->error($message, $context);
        }
    }

    /**
     * Log info if logging is enabled
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if (Config::get('swedbank.logging.enabled', true)) {
            Log::channel(Config::get('swedbank.logging.channel', 'swedbank'))
                ->info($message, $context);
        }
    }
}

