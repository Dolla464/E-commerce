<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckOutController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/me', [AuthController::class, 'me']);
//     Route::get('/token', [AuthController::class, 'getAccessToken']);
// });

Route::get('/health', [TestController::class, 'check']);


Route::apiResource('products', ProductController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'permission:create products'])->group(function() {
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    Route::post('products/{id}/restore', [ProductController::class, 'undoDelete'])->name('products.restore');
    Route::delete('products/{id}/force', [ProductController::class, 'permenantDelete'])->name('products.forceDelete');
});

Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'permission:create categories'])->group(function() {
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});

Route::middleware(['auth:sanctum', 'permission:create orders'])->group(function() {
    Route::delete('cart/clear', [CartController::class, 'clear'])->name('cart.clear');
    Route::apiResource('cart', CartController::class)->except(['show']);
});

Route::middleware(['auth:sanctum', 'permission:create orders'])->group(function () {
    Route::post('checkout', [CheckOutController::class, 'checkout'])->name('checkout');
    Route::get('orders', [CheckOutController::class, 'orders'])->name('orders.list');
    Route::get('orders/{orderId}', [CheckOutController::class, 'orderDetails'])->name('orders.details');
    // handle payment routes
    Route::post('orders/{order}/payments', [PaymentController::class, 'createPayment'])->name('payments.create');
    // confirm payment status
    Route::get('payments/{paymentId}/confirm', [PaymentController::class, 'confirmPayment'])->name('payments.confirm');
});

// webhook route for stripe
Route::post('webhooks/stripe', [PaymentController::class, 'stripeWebhook'])->name('webhooks.stripe');



include_once __DIR__.'/auth.php';
