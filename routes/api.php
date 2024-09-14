<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');

Route::middleware('auth:api')->group(function () {
    Route::get('profile', [AuthController::class, 'userProfile'])->middleware('auth:api');
    Route::post('transactions', [TransactionController::class, 'store']); // Record a new transaction
    Route::get('transactions', [TransactionController::class, 'index']);  // List transactions with optional type filter
});
