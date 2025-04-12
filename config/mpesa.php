<?php

return [
    'base_url' => env('MPESA_BASE_URL','https://sandbox.safaricom.co.ke'),
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'shortcode' => env('MPESA_SHORTCODE'),
    'passkey' => env('MPESA_PASSKEY'),
    'callback_url' => env('CALLBACK_URL'),
    'query_url' => env('MPESA_QUERY_URL'),
    'mpesa_password'=> env('MPESA_PASSWORD'),
    'mpesa_c2b_register_url'=> env('MPESA_C2B_REGISTER_URL'),
];