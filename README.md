# **📌 MPESA Integration in Laravel**
This guide will help you integrate **M-Pesa STK Push** into a Laravel project using the **Daraja API**.

## **📌 Prerequisites**
1. Laravel Installed (Preferably Laravel 10+)
2. PHP 8.0 or higher
3. Composer Installed
4. MySQL Database Configured
5. **[Safaricom Daraja Developer Account](https://developer.safaricom.co.ke/)**

---

## **🚀 Setup Laravel Project**
```bash
composer create-project --prefer-dist laravel/laravel mpesa-integration
cd mpesa-integration
```

---

## **📌 Install Dependencies**
Install **Guzzle HTTP Client** for API requests:
```bash
composer require guzzlehttp/guzzle
```

---

## **📌 Create Payment Controller**
```bash
php artisan make:controller Payments/PaymentController
```

---

## **📌 Configure M-Pesa Credentials**
Create a new file at `config/mpesa.php` and register your `.env` M-Pesa details.

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
    'timeout_url' => env('MPESA_TIMEOUT_URL', env('MPESA_CALLBACK_URL')),
];
```

### **Update your `.env` file with M-Pesa credentials**
```ini
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_passkey
MPESA_CALLBACK_URL=https://your-ngrok-url.com/api/stk_callback
MPESA_TIMEOUT_URL=https://your-ngrok-url.com/api/timeout
```

---

## **📌 Generate M-Pesa Access Token**
Inside **`PaymentController.php`**, create a method to fetch the **access token**:

```php
use Illuminate\Support\Facades\Http;

public function getAccessToken()
{
    $consumerKey = config('mpesa.consumer_key');
    $consumerSecret = config('mpesa.consumer_secret');
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $response = Http::withBasicAuth($consumerKey, $consumerSecret)->get($url);

    return $response->json()['access_token'] ?? null;
}
```

---

## **📌 Implement STK Push (Lipa Na M-Pesa)**
Create a method inside **PaymentController.php**:

```php
use Carbon\Carbon;

public function initiateSTKPush()
{
    $AccessToken = $this->getAccessToken();
    $PassKey = config('mpesa.passkey');
    $BusinessShortCode = config('mpesa.shortcode');
    $Timestamp = Carbon::now()->format('YmdHis');
    $Password = base64_encode($BusinessShortCode.$PassKey.$Timestamp);

    $TransactionType = "CustomerPayBillOnline";
    $Amount = 1;
    $PhoneNumber = "254701838170"; // Hardcoded for testing
    $CallBackURL = config('mpesa.callback_url');
    $AccountReference = "Test Transaction";
    $TransactionDesc = "Payment for services";

    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $response = Http::withToken($AccessToken)->post($url, [
        'BusinessShortCode' => $BusinessShortCode,
        'Timestamp' => $Timestamp,
        'Password' => $Password,
        'TransactionType' => $TransactionType,
        'Amount' => $Amount,
        'PartyA' => $PhoneNumber,
        'PartyB' => $BusinessShortCode,
        'PhoneNumber' => $PhoneNumber,
        'CallBackURL' => $CallBackURL,
        'AccountReference' => $AccountReference,
        'TransactionDesc' => $TransactionDesc,
    ]);

    return response()->json($response->json());
}
```

---

## **📌 Handle STK Push Callback**
Modify `PaymentController.php` to process M-Pesa responses:

```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

public function STKCallBack(Request $request)
{
    $data = file_get_contents('php://input');
    
    Log::info("STK Callback Response: " . $data);

    Storage::put('stk_callback_debug.txt', $data);

    return response()->json(['message' => 'Callback received successfully']);
}
```

---

## **📌 Define API Routes**
Inside `routes/api.php`:

```php
use App\Http\Controllers\Payments\PaymentController;

Route::get('/mpesa/token', [PaymentController::class, 'getAccessToken']);
Route::post('/mpesa/stkpush', [PaymentController::class, 'initiateSTKPush']);
Route::post('/mpesa/stk_callback', [PaymentController::class, 'STKCallBack']);
```

---

## **🌍 Expose Laravel to Safaricom (NGROK)**
If running **locally**, Safaricom **cannot** access your callback URL. Use **ngrok**:

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
Copy the **HTTPS URL** provided by ngrok and update `MPESA_CALLBACK_URL` in `.env`.

---

## **📌 Test the Integration**
### **1️⃣ Generate Access Token**
```bash
curl -X GET http://127.0.0.1:8000/api/mpesa/token
```

### **2️⃣ Initiate STK Push**
```bash
curl -X POST http://127.0.0.1:8000/api/mpesa/stkpush
```
- You should receive a payment prompt on your phone.

### **3️⃣ Test Callback with Postman**
Use **POST** request:
```json
{
   "Body": {        
      "stkCallback": {            
         "MerchantRequestID": "29115-34620561-1",            
         "CheckoutRequestID": "ws_CO_191220191020363925",            
         "ResultCode": 0,            
         "ResultDesc": "Success",            
         "CallbackMetadata": {                
            "Item": [{                        
               "Name": "Amount",                        
               "Value": 1.00                    
            },                    
            {                        
               "Name": "MpesaReceiptNumber",                        
               "Value": "NLJ7RT61SV"                    
            },                    
            {                        
               "Name": "TransactionDate",                        
               "Value": 20250329123045                    
            },                    
            {                        
               "Name": "PhoneNumber",                        
               "Value": 254701838170                    
            }]            
         }        
      }    
   }
}
```

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
✅ Using NGROK to expose your local server  

---

