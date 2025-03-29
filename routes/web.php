<?php

use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::controller(PaymentController::class)
    ->prefix('payments')
    ->as('payments')
    ->group(function () {
        Route::get('/token','getAccessToken')->name('get.access.token');
        Route::get('/initiate-STK-push','initiateSTKPush')->name('get.initiate.push');

        // 
        Route::post('/STKCallBack','STKCallback')->name('post.stk.callback');
    });