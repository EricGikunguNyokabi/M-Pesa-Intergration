<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>





## MPESA INTERGRATION

```bash
php artisan make:controller Payments/PaymentController

# create a function to get access token

# create a file mpesa.php in config/ and register your .env mpesa details 
# config/mpesa.php 
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


# create a method to get access token 
    public function getAccessToken(){

        $consumerKey = config('mpesa.consumer_key');
        $consumerSecret = config('mpesa.consumer_secret');
        $url = config('mpesa.timeout_url');

        $response = Http::withBasicAuth($consumerKey, $consumerSecret)->get($url);
        return $response; //this displays the servers message
    }

# stk push 
composer require guzzlehttp/guzzle 
```
- **[Safaricom - Daraja Developer's Portal](https://developer.safaricom.co.ke/)**
# mpesa https://developer.safaricom.co.ke/APIs
- select __M-Pesa Express => simulate


# install ngrok
https://dashboard.ngrok.com/signup
ngrok config add-authtoken YOUR_AUTH_TOKEN


https://82f5-102-216-154-137.ngrok-free.app/payments/initiate-STK-push
```
