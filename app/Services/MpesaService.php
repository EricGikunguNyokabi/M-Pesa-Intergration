<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MpesaService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $passkey;
    protected $shortCode;
    protected $baseUrl;
    protected $mpesaPassword;


    public function __construct()
    {
        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
        $this->passkey = config('mpesa.passkey');
        $this->shortCode = config('mpesa.shortcode');
        $this->baseUrl = config('mpesa.base_url'); 
        $this->mpesaPassword = config('mpesa.mpesa_password');
    }

    /**
     * Generate an access token for M-Pesa API.
     */
    public function generateAccessToken()
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

        return $response->json()['access_token'];
    }

    /**
     * Initiate an STK Push request.
     */
    public function stkPush($amount, $phone, $reference, $description)
    {
        $accessToken = $this->generateAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->shortCode,
            'Password' => base64_encode($this->shortCode . $this->passkey . now()->format('YmdHis')),
            'Timestamp' => now()->format('YmdHis'),
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortCode,
            'PhoneNumber' => $phone,
            'CallBackURL' => config('mpesa.callback_url') . '/api/mpesa-callback',
            'AccountReference' => $reference,
            'TransactionDesc' => $description,
        ]);

        return $response->json();
    }

    /**
     * Query the status of a transaction.
     */
    public function transactionStatus(string $checkoutRequestId)
    {
        // Generate the password
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortCode . $this->passkey . $timestamp);
    
        // Get the access token
        $accessToken = $this->generateAccessToken();
    
        // Make the HTTP POST request to the M-Pesa API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post('https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $this->shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    
        // Log the raw response for debugging
        \Log::info('Raw M-Pesa API Response', ['response' => $response->body()]);
    
        // Return the response as JSON
        return $response->json();
    }

    /**
     * Send B2C payment.
     */
    public function b2cPayment($amount, $phone, $commandId)
    {
        $accessToken = $this->generateAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post('https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest', [
            'InitiatorName' => 'YourInitiatorName',
            'SecurityCredential' => 'YourSecurityCredential',
            'CommandID' => $commandId,
            'Amount' => $amount,
            'PartyA' => $this->shortCode,
            'PartyB' => $phone,
            'Remarks' => 'Salary Payment',
            'QueueTimeOutURL' => config('app.url') . '/api/mpesa-callback',
            'ResultURL' => config('app.url') . '/api/mpesa-callback',
            'Occasion' => 'Salary Payment',
        ]);

        return $response->json();
    }

/**
 * Register C2B URLs with M-Pesa.
 */
public function registerC2BUrls(string $shortCode, string $responseType, string $confirmationUrl, ?string $validationUrl = null)
{
    // Get the access token
    $accessToken = $this->generateAccessToken();

    // Make the HTTP POST request to the M-Pesa API
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
    ])->post($this->baseUrl . '/mpesa/c2b/v1/registerurl', [
        'ShortCode' => $shortCode,
        'ResponseType' => $responseType,
        'ConfirmationURL' => $confirmationUrl,
        'ValidationURL' => $validationUrl ?? '',
    ]);

    // Log the raw response for debugging
    \Log::info('Raw M-Pesa API Response for C2B Register URL', ['response' => $response->body()]);

    // Check if the response is valid JSON
    if ($response->successful()) {
        return $response->json();
    }

    // Log an error if the response is invalid or empty
    \Log::error('Invalid or Empty Response from M-Pesa API for C2B Register URL', [
        'status_code' => $response->status(),
        'response_body' => $response->body(),
    ]);

    return null;
}
}