<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;

class MpesaController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Initiate an STK Push request.
     */
    public function stkPush(Request $request)
    {
        \Log::info('STK Push Request Initiated', ['request_data' => $request->all()]);

        // Validate the request data
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric',
                'phone' => 'required|string',
                'reference' => 'required|string',
                'description' => 'nullable|string',
            ]);

            \Log::info('STK Push Validation Passed', ['validated_data' => $validated]);

            } catch (\Exception $e) {
                \Log::error('STK Push Validation Failed', ['error' => $e->getMessage()]);
                return response()->json(['status' => 'error', 'message' => 'Validation failed'], 422);
            }


        // Call the service method
        try {
            \Log::info('Calling MpesaService for STK Push', ['data' => $validated]);
            $response = $this->mpesaService->stkPush(
                $validated['amount'],
                $validated['phone'],
                $validated['reference'],
                $validated['description'] ?? 'Test Payment'
            );
            \Log::info('STK Push Response Received', ['response' => $response]);
        } catch (\Exception $e) {
            \Log::error('Error During STK Push Service Call', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to initiate STK push'], 500);
        }

        return response()->json($response);
    }

    /**
     * Handle M-Pesa callback responses.
     */
    public function mpesaCallback(Request $request)
    {
        // Log the incoming data for debugging
        \Log::info('M-Pesa Callback Data Received', ['callback_data' => $request->all()]);

        // Extract the callback data
        $callbackData = $request->input('Body.stkCallback');

        if ($callbackData['ResultCode'] === 0) {
            \Log::info('M-Pesa Transaction Successful', ['callback_data' => $callbackData]);

            // Extract metadata
            $metadata = $callbackData['CallbackMetadata']['Item'];
            $amount = null;
            $receiptNumber = null;
            $transactionDate = null;
            $phoneNumber = null;

            foreach ($metadata as $item) {
                switch ($item['Name']) {
                    case 'Amount':
                        $amount = $item['Value'];
                        break;
                    case 'MpesaReceiptNumber':
                        $receiptNumber = $item['Value'];
                        break;
                    case 'TransactionDate':
                        $transactionDate = $item['Value'];
                        break;
                        case 'PhoneNumber':
                            $phoneNumber = $item['Value'];
                            break;
                    }
                }
            // Log extracted transaction details
            \Log::info('Extracted Transaction Details', [
                'amount' => $amount,
                'receipt_number' => $receiptNumber,
                'transaction_date' => $transactionDate,
                'phone_number' => $phoneNumber,
            ]);

            // Save the transaction details to the database 
            try {
                MpesaTransaction::create([
                    'merchant_request_id' => $callbackData['MerchantRequestID'],
                    'checkout_request_id' => $callbackData['CheckoutRequestID'],
                    'amount' => $amount,
                    'mpesa_receipt_number' => $receiptNumber,
                    'transaction_date' => $transactionDate,
                    'phone_number' => $phoneNumber,
                    'result_code' => $callbackData['ResultCode'],
                    'result_desc' => $callbackData['ResultDesc'],
                ]);
                \Log::info('Mpesa Transaction Saved to Database');
            } catch (\Exception $e) {
                \Log::error('Failed to Save Mpesa Transaction to Database', ['error' => $e->getMessage()]);
            }
            
            return response()->json(['status' => 'success', 'message' => 'Payment received']);
        } else {
            \Log::error('M-Pesa Transaction Failed', ['result_code' => $callbackData['ResultCode'], 'result_desc' => $callbackData['ResultDesc']]);
            return response()->json(['status' => 'failed', 'message' => $callbackData['ResultDesc']]);
        }
    }


    /**
     * Query the status of an STK Push transaction.
     */
    public function queryStkStatus(Request $request)
    {
        \Log::info('STK Query Request Initiated', ['request_data' => $request->all()]);
    
        // Validate the request data
        try {
            $validated = $request->validate([
                'checkout_request_id' => 'required|string',
            ]);
            \Log::info('STK Query Validation Passed', ['validated_data' => $validated]);
        } catch (\Exception $e) {
            \Log::error('STK Query Validation Failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Validation failed'], 422);
        }
    
        // Call the service method to query the transaction status
        try {
            \Log::info('Calling MpesaService for STK Query', ['data' => $validated]);
            $response = $this->mpesaService->transactionStatus($validated['checkout_request_id']);
            \Log::info('STK Query Response Received', ['response' => $response]);
        } catch (\Exception $e) {
            \Log::error('Error During STK Query Service Call', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to query transaction status'], 500);
        }
    
        return response()->json($response);
    }


/**
 * Register C2B URLs with M-Pesa.
 */
public function registerC2BUrls(Request $request)
{
    \Log::info('C2B Register URL Request Initiated', ['request_data' => $request->all()]);

    // Validate the request data
    try {
        $validated = $request->validate([
            'short_code' => 'required|string',
            'response_type' => 'required|string|in:Completed,Cancelled',
            'confirmation_url' => 'required|url',
            'validation_url' => 'nullable|url',
        ]);
        \Log::info('C2B Register URL Validation Passed', ['validated_data' => $validated]);
    } catch (\Exception $e) {
        \Log::error('C2B Register URL Validation Failed', ['error' => $e->getMessage()]);
        return response()->json(['status' => 'error', 'message' => 'Validation failed'], 422);
    }

    // Call the service method to register the URLs
    try {
        \Log::info('Calling MpesaService for C2B Register URL', ['data' => $validated]);
        $response = $this->mpesaService->registerC2BUrls(
            $validated['short_code'],
            $validated['response_type'],
            $validated['confirmation_url'],
            $validated['validation_url']
        );
        \Log::info('C2B Register URL Response Received', ['response' => $response]);
    } catch (\Exception $e) {
        \Log::error('Error During C2B Register URL Service Call', ['error' => $e->getMessage()]);
        return response()->json(['status' => 'error', 'message' => 'Failed to register C2B URLs'], 500);
    }

    return response()->json($response);
}



/**
 * Handle C2B Confirmation Requests.
 */
/**
 * Handle C2B Confirmation Requests.
 */
public function c2bConfirmation(Request $request)
{
    \Log::info('C2B Confirmation Request Received', ['data' => $request->all()]);

    // Process the confirmation data (e.g., save to database)
    try {
        MpesaTransaction::create([
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

        \Log::info('C2B Confirmation Data Saved to Database');
    } catch (\Exception $e) {
        \Log::error('Failed to Save C2B Confirmation Data', ['error' => $e->getMessage()]);
    }

    // Respond to Safaricom
    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Success',
    ]);
}

/**
 * Handle C2B Validation Requests.
 */
public function c2bValidation(Request $request)
{
    \Log::info('C2B Validation Request Received', ['data' => $request->all()]);

    // Perform any validation logic here (optional)
    // Example: Check if the amount matches expected values

    // Respond to Safaricom
    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
}





    /**
     * Send B2C payment.
     */
    // public function b2cPayment(Request $request)
    // {

    //     \Log::info('B2C Payment Request Initiated', ['request_data' => $request->all()]);

    //     // Validate the request data
    //     try {
    //         $validated = $request->validate([
    //             'amount' => 'required|numeric',
    //             'phone' => 'required|string',
    //             'command_id' => 'required|string',
    //         ]);
    //         \Log::info('B2C Payment Validation Passed', ['validated_data' => $validated]);
    //     } catch (\Exception $e) {
    //         \Log::error('B2C Payment Validation Failed', ['error' => $e->getMessage()]);
    //         return response()->json(['status' => 'error', 'message' => 'Validation failed'], 422);
    //     }

    //     // Call the service method
    //     try {
    //         \Log::info('Calling MpesaService for B2C Payment', ['data' => $validated]);
    //         $response = $this->mpesaService->b2cPayment(
    //             $validated['amount'],
    //             $validated['phone'],
    //             $validated['command_id']
    //         );
    //         \Log::info('B2C Payment Response Received', ['response' => $response]);
    //     } catch (\Exception $e) {
    //         \Log::error('Error During B2C Payment Service Call', ['error' => $e->getMessage()]);
    //         return response()->json(['status' => 'error', 'message' => 'Failed to send B2C payment'], 500);
    //     }

    //     return response()->json($response);
    // }


}