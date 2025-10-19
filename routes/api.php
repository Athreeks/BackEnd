<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CartController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('admin')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::patch('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
    });

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::get('orders/active', [OrderController::class, 'getActiveOrders']);
    Route::get('orders/history', [OrderController::class, 'getHistoryOrders']);
    Route::apiResource('orders', OrderController::class);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    Route::get('/cart', [CartController::class, 'index']);      // Melihat isi keranjang
    Route::post('/cart', [CartController::class, 'store']);     // Menambah item ke keranjang
    Route::put('/cart/{cart}', [CartController::class, 'update']);  // Mengubah jumlah item
    Route::delete('/cart/{cart}', [CartController::class, 'destroy']); // Menghapus item dari keranjang
    Route::post('/checkout', [OrderController::class, 'checkout']);
});
