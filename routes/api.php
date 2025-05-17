<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductViewController;
use App\Http\Controllers\ProductRecommendationController;

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

// Member routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/members/register', [MemberController::class, 'register']);
    Route::post('/members/fcm-token', [MemberController::class, 'updateFcmToken']);
    Route::get('/members/team-structure', [MemberController::class, 'getTeamStructure']);
});

// Commission routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/commissions/calculate', [CommissionController::class, 'calculateCommissions']);
    Route::get('/commissions/history', [CommissionController::class, 'getCommissionHistory']);
});

// Order routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/complete', [OrderController::class, 'completeOrder']);
});

// Product View Routes
Route::prefix('products')->group(function () {
    Route::post('{product}/view', [ProductViewController::class, 'trackView']);
    Route::get('popular', [ProductViewController::class, 'getPopularProducts']);
    Route::get('trending', [ProductViewController::class, 'getTrendingProducts']);
    Route::get('{product}/stats', [ProductViewController::class, 'getProductStats']);
});

// Product recommendation routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products/recommendations', [ProductRecommendationController::class, 'getPersonalizedRecommendations']);
    Route::get('/products/trending', [ProductRecommendationController::class, 'getTrendingProducts']);
    Route::get('/products/category/{categoryId}/popular', [ProductRecommendationController::class, 'getPopularProductsByCategory']);
    Route::get('/products/{productId}/similar', [ProductRecommendationController::class, 'getSimilarProducts']);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_middleware')
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

require __DIR__.'/auth.php'; 