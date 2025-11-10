<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| Rute Publik (Tidak Perlu Login)
|--------------------------------------------------------------------------
*/

// Autentikasi
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Webhook (HARUS publik)
Route::post('/payment-webhook', [WebhookController::class, 'handle']);

// ❗️ RUTE PRODUK (BACA) KITA PINDAHKAN KE SINI AGAR PUBLIK ❗️
// Ini adalah perbaikan untuk DashboardGuest.vue
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Rute Terproteksi (Wajib Login / auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Autentikasi
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']); // Untuk update nama, alamat, foto

    // Keranjang (Cart)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{cart}', [CartController::class, 'update']);
    Route::delete('/cart/{cart}', [CartController::class, 'destroy']);

    // Pesanan (Order)
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('orders/active', [OrderController::class, 'getActiveOrders']);
    Route::get('orders/history', [OrderController::class, 'getHistoryOrders']);
    // (apiResource untuk show, update, destroy)
    Route::apiResource('orders', OrderController::class)->except(['index', 'store']);


    /*
    |--------------------------------------------------------------------------
    | Rute Khusus Admin (Wajib Login & Role Admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {

        // ❗️ HANYA ADMIN YANG BOLEH MEMBUAT, MENGUBAH, MENGHAPUS PRODUK
        Route::post('products', [ProductController::class, 'store']);
        Route::post('products/{product}', [ProductController::class, 'update']); // (Handle 'form-data' _method=PUT)
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        // (Anda bisa tambahkan rute lain khusus admin di sini jika perlu)

    });
});
