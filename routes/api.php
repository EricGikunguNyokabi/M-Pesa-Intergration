<?php

use App\Http\Controllers\MpesaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// STK Push Route
Route::post('/mpesa/stk-push', [MpesaController::class, 'stkPush'])->name('api.stk-push');


// Callback URL for M-Pesa Responses
Route::post('/mpesa/mpesa-stk-callback', [MpesaController::class, 'mpesaCallback'])->name('api.mpesa-callback');

// Transaction Status Query Route
Route::post('/mpesa/stk-transaction-status', [MpesaController::class, 'queryStkStatus'])->name('api.transaction-status');


// B2C Payment Route
Route::post('/mpepap/register-c2b', [MpesaController::class, 'registerC2BUrls']);

// C2B Confirmation URL
Route::post('/mpepap/c2b/confirmation', [MpesaController::class, 'c2bConfirmation']);

// C2B Validation URL (optional)
Route::post('/mpepap/c2b/validation', [MpesaController::class, 'c2bValidation']);