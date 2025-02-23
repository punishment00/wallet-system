<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
Route::get('/wallet/{walletId}', [WalletController::class, 'walletBalance']);
