<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function getAccessToken()
    {
        $consumerKey = config('mpesa.consumer_key');
        $consumerSecret = config('mpesa.consumer_secret');
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withBasicAuth($consumerKey, $consumerSecret)->get($url);

        return $response['access_token']; // Get the access token
    }

    public function initiateSTKPush()
    {
        $AccessToken = $this->getAccessToken();
        $PassKey = config('mpesa.passkey');
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $BusinessShortCode = config('mpesa.shortcode');
        $Timestamp = Carbon::now()->format('YmdHis');
        $Password = base64_encode($BusinessShortCode . $PassKey . $Timestamp);
        $TransactionType = "CustomerPayBillOnline";
        $Amount = "0";
        $PartyA = "254701838170"; // Hardcoded number
        $PartyB = config('mpesa.shortcode');
        $PhoneNumber = "254701838170";
        $CallBackURL = 'https://82f5-102-216-154-137.ngrok-free.app/payments/STKCallBack'; //config('mpesa.callback_url');
        $AccountReference = "Account Reference Test";
        $TransactionDesc = "Test";

        $response = Http::withToken($AccessToken)->post($url, [
            'BusinessShortCode' => $BusinessShortCode,
            'Timestamp' => $Timestamp,
            'Password' => $Password,
            'TransactionType' => $TransactionType,
            'Amount' => $Amount,
            'PartyA' => $PartyA,
            'PartyB' => $PartyB,
            'PhoneNumber' => $PhoneNumber,
            'CallBackURL' => $CallBackURL,
            'AccountReference' => $AccountReference,
            'TransactionDesc' => $TransactionDesc,
        ]);

        $responseData = $response->json();

        if (isset($responseData['MerchantRequestID']) && isset($responseData['CheckoutRequestID'])) {
            // Save initial transaction
            MpesaTransaction::create([
                'merchant_request_id' => $responseData['MerchantRequestID'],
                'checkout_request_id' => $responseData['CheckoutRequestID'],
                'amount' => $Amount,
                'phone_number' => $PhoneNumber,
                'result_code' => -1, // Default pending status
                'result_desc' => 'Pending', // Default value
                'mpesa_receipt_number' => null,
                'transaction_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $responseData;
    }

    public function STKCallBack(Request $request)
    {
        $data = file_get_contents('php://input');
        Log::info("STK Callback Response: " . $data);
        Storage::disk('local')->put('stk_callback_debug.txt', $data); // Save response for debugging

        $mpesaResponse = json_decode($data, true);

        if (isset($mpesaResponse['Body']['stkCallback'])) {
            $callback = $mpesaResponse['Body']['stkCallback'];

            $transaction = MpesaTransaction::where('checkout_request_id', $callback['CheckoutRequestID'])->first();

            if ($transaction) {
                $updateData = [];

                // Ensure default values if fields are missing
                $updateData['result_code'] = $callback['ResultCode'] ?? -1; 
                $updateData['result_desc'] = $callback['ResultDesc'] ?? 'No description available';

                // Check if CallbackMetadata exists before trying to access it
                if (isset($callback['CallbackMetadata']['Item']) && is_array($callback['CallbackMetadata']['Item'])) {
                    $metadata = collect($callback['CallbackMetadata']['Item']);

                    $updateData['amount'] = optional($metadata->firstWhere('Name', 'Amount'))['Value'] ?? $transaction->amount;
                    $updateData['mpesa_receipt_number'] = optional($metadata->firstWhere('Name', 'MpesaReceiptNumber'))['Value'] ?? null;
                    $updateData['transaction_date'] = optional($metadata->firstWhere('Name', 'TransactionDate'))['Value'] ?? null;
                    $updateData['phone_number'] = optional($metadata->firstWhere('Name', 'PhoneNumber'))['Value'] ?? $transaction->phone_number;
                }

                if (!empty($updateData)) {
                    $transaction->update($updateData);
                }
            }
        }

        return response()->json(['message' => 'STK Callback processed'], 200);
    }
}
