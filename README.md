# **📌 MPESA Integration in Laravel**
This guide will help you integrate **M-Pesa APIs** into a Laravel project using the **Daraja API**. It covers **STK Push**, **C2B (Customer-to-Business)**, and **Callback Handling**.

---

## **📌 Prerequisites**
1. **Laravel Installed**: Preferably Laravel 10+.
2. **PHP Version**: PHP 8.0 or higher.
3. **Composer Installed**: For managing dependencies.
4. **MySQL Database**: Configured for storing transaction details.
5. **[Safaricom Daraja Developer Account](https://developer.safaricom.co.ke/)**: To obtain API credentials.

---

## **🚀 Setup Laravel Project**
```bash
composer create-project --prefer-dist laravel/laravel mpesa-integration
cd mpesa-integration
```

---

## **📌 Install Dependencies**
Install **Guzzle HTTP Client** for making API requests:
```bash
composer require guzzlehttp/guzzle
```

---

## **📌 Configure M-Pesa Credentials**
Create a new file at `config/mpesa.php` to store your M-Pesa configuration:

### **config/mpesa.php**
```php
<?php

return [
    'env' => env('MPESA_ENV', 'sandbox'),
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'shortcode' => env('MPESA_SHORTCODE'),
    'passkey' => env('MPESA_PASSKEY'),
    'callback_url' => env('MPESA_CALLBACK_URL'),
    'validation_url' => env('MPESA_VALIDATION_URL'),
    'timeout_url' => env('MPESA_TIMEOUT_URL', env('MPESA_CALLBACK_URL')),
];
```

### **Update `.env` File**
Add the following M-Pesa credentials to your `.env` file:
```ini
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_passkey
MPESA_CALLBACK_URL=https://your-ngrok-url.com/api/mpesa/stk_callback
MPESA_VALIDATION_URL=https://your-ngrok-url.com/api/mpesa/c2b/validation
MPESA_TIMEOUT_URL=https://your-ngrok-url.com/api/mpesa/timeout
```

---

## **📌 Generate M-Pesa Access Token**
Create a method in your `MpesaService` class to fetch the access token:

```php
use Illuminate\Support\Facades\Http;

public function getAccessToken()
{
    $consumerKey = config('mpesa.consumer_key');
    $consumerSecret = config('mpesa.consumer_secret');
    $url = config('mpesa.env') === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $response = Http::withBasicAuth($consumerKey, $consumerSecret)->get($url);

    return $response->json()['access_token'] ?? null;
}
```

---

## **📌 Implement STK Push (Lipa Na M-Pesa)**
Create a method in your `MpesaService` class to initiate an STK Push request:

```php
use Carbon\Carbon;

public function stkPush($amount, $phone, $reference, $description = 'Test Payment')
{
    $accessToken = $this->getAccessToken();
    $shortCode = config('mpesa.shortcode');
    $passkey = config('mpesa.passkey');
    $timestamp = Carbon::now()->format('YmdHis');
    $password = base64_encode($shortCode . $passkey . $timestamp);

    $url = config('mpesa.env') === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $response = Http::withToken($accessToken)->post($url, [
        'BusinessShortCode' => $shortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $shortCode,
        'PhoneNumber' => $phone,
        'CallBackURL' => config('mpesa.callback_url'),
        'AccountReference' => $reference,
        'TransactionDesc' => $description,
    ]);

    return $response->json();
}
```

---

## **📌 Handle STK Push Callback**
Create a method in your `MpesaController` to handle the callback response:

```php
use Illuminate\Support\Facades\Log;

public function stkCallback(Request $request)
{
    $data = $request->all();

    Log::info('STK Callback Response Received', ['data' => $data]);

    // Extract transaction details
    $callbackData = $data['Body']['stkCallback'];
    if ($callbackData['ResultCode'] === 0) {
        $metadata = $callbackData['CallbackMetadata']['Item'];
        $transactionDetails = [];
        foreach ($metadata as $item) {
            $transactionDetails[$item['Name']] = $item['Value'];
        }

        // Save transaction to database
        \App\Models\MpesaTransaction::create([
            'merchant_request_id' => $callbackData['MerchantRequestID'],
            'checkout_request_id' => $callbackData['CheckoutRequestID'],
            'amount' => $transactionDetails['Amount'] ?? null,
            'mpesa_receipt_number' => $transactionDetails['MpesaReceiptNumber'] ?? null,
            'transaction_date' => $transactionDetails['TransactionDate'] ?? null,
            'phone_number' => $transactionDetails['PhoneNumber'] ?? null,
            'result_code' => $callbackData['ResultCode'],
            'result_desc' => $callbackData['ResultDesc'],
        ]);
    } else {
        Log::error('STK Callback Failed', ['result_code' => $callbackData['ResultCode'], 'result_desc' => $callbackData['ResultDesc']]);
    }

    return response()->json(['message' => 'Callback received successfully']);
}
```

---

## **📌 Register C2B URLs**
To handle incoming payments, register your **Validation URL** and **Confirmation URL** with M-Pesa.

### **Register C2B URLs**
Create a method in your `MpesaService` class to register the URLs:

```php
public function registerC2BUrls($shortCode, $responseType, $confirmationUrl, $validationUrl = null)
{
    $accessToken = $this->getAccessToken();
    $url = config('mpesa.env') === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl'
        : 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl';

    $response = Http::withToken($accessToken)->post($url, [
        'ShortCode' => $shortCode,
        'ResponseType' => $responseType,
        'ConfirmationURL' => $confirmationUrl,
        'ValidationURL' => $validationUrl ?? '',
    ]);

    return $response->json();
}
```

---

## **📌 Handle C2B Validation and Confirmation**
Create methods in your `MpesaController` to handle validation and confirmation requests:

### **Validation Request**
```php
public function c2bValidation(Request $request)
{
    Log::info('C2B Validation Request Received', ['data' => $request->all()]);

    // Respond to Safaricom
    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Success',
    ]);
}
```

### **Confirmation Request**
```php
public function c2bConfirmation(Request $request)
{
    Log::info('C2B Confirmation Request Received', ['data' => $request->all()]);

    // Save transaction to database
    \App\Models\MpesaTransaction::create([
        'transaction_type' => $request->input('TransactionType'),
        'trans_id' => $request->input('TransID'),
        'trans_time' => $request->input('TransTime'),
        'trans_amount' => $request->input('TransAmount'),
        'business_short_code' => $request->input('BusinessShortCode'),
        'bill_ref_number' => $request->input('BillRefNumber'),
        'invoice_number' => $request->input('InvoiceNumber'),
        'phone_number' => $request->input('MSISDN'),
        'first_name' => $request->input('FirstName'),
        'middle_name' => $request->input('MiddleName'),
        'last_name' => $request->input('LastName'),
    ]);

    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Success',
    ]);
}
```

---

## **📌 Define API Routes**
Define routes in `routes/api.php`:

```php
use App\Http\Controllers\MpesaController;

Route::post('/mpesa/stk-push', [MpesaController::class, 'stkPush']);
Route::post('/mpesa/stk-callback', [MpesaController::class, 'stkCallback']);
Route::post('/mpesa/register-c2b', [MpesaController::class, 'registerC2BUrls']);
Route::post('/mpesa/c2b/validation', [MpesaController::class, 'c2bValidation']);
Route::post('/mpesa/c2b/confirmation', [MpesaController::class, 'c2bConfirmation']);
```

---

## **🌍 Expose Laravel to Safaricom (NGROK)**
If running locally, use **ngrok** to expose your server:

### **Step 1: Install NGROK**
```bash
wget https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip
unzip ngrok-stable-linux-amd64.zip
mv ngrok /usr/local/bin
```

### **Step 2: Authenticate NGROK**
```bash
ngrok config add-authtoken YOUR_AUTH_TOKEN
```

### **Step 3: Start NGROK**
```bash
ngrok http 8000
```
Copy the HTTPS URL provided by ngrok and update your `.env` file with the new URLs.

---

## **📌 Test the Integration**
### **1️⃣ Generate Access Token**
```bash
curl -X GET http://127.0.0.1:8000/api/mpesa/token
```

### **2️⃣ Initiate STK Push**
```bash
curl -X POST http://127.0.0.1:8000/api/mpesa/stk-push \
-H "Content-Type: application/json" \
-d '{"amount": 1, "phone": "254701838170", "reference": "Test Reference"}'
```

### **3️⃣ Simulate C2B Payment**
Use the sandbox environment to simulate a payment.

---

## **📌 Useful Links**
- **[Safaricom - Daraja Developer's Portal](https://developer.safaricom.co.ke/)**
- **[My Apps Dashboard](https://developer.safaricom.co.ke/MyApps)**
- **[M-Pesa Express API](https://developer.safaricom.co.ke/APIs)**

---

## **🎯 Conclusion**
This guide walks you through:
✅ Setting up Laravel for M-Pesa  
✅ Fetching an access token  
✅ Performing an STK push  
✅ Handling M-Pesa callbacks  
✅ Registering C2B URLs  
✅ Using NGROK to expose your local server  

You now have a fully functional M-Pesa integration in your Laravel application! 🚀












# Guzzle client is a PHP HTTP client that makes it easy to send HTTP requests. Install it via Composer:
composer require guzzlehttp/guzzle


# Bind the Service in a Service Provider


public function register()
{
    $this->app->singleton(MpesaService::class, function () {
        return new MpesaService();
    });
}