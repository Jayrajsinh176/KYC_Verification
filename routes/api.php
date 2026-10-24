<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\RetailerController;
use App\Http\Controllers\Api\KycServiceController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RetailerDashboardController;

/*
|--------------------------------------------------------------------------
| VerifyHub B2B KYC API Routes
|--------------------------------------------------------------------------
*/

// 1. Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/retailer/login', [AuthController::class, 'retailerLogin']);

    // Authenticated Session Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// 2. Protected Super Admin Endpoints
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Overview / Dashboard KPIs & Analytics
    Route::get('/overview', [AdminDashboardController::class, 'overview']);

    // Retailers Management
    Route::get('/retailers', [RetailerController::class, 'index']);
    Route::post('/retailers', [RetailerController::class, 'store']);
    Route::put('/retailers/{id}', [RetailerController::class, 'update']);
    Route::put('/retailers/{id}/status', [RetailerController::class, 'toggleStatus']);

    // Services & Pricing Switchboard
    Route::get('/services', [KycServiceController::class, 'index']);
    Route::put('/services/{id}', [KycServiceController::class, 'update']);
    Route::get('/pricing', [PricingController::class, 'index']);
    Route::post('/pricing/defaults', [PricingController::class, 'saveDefaults']);
    Route::post('/pricing/override', [PricingController::class, 'saveRetailerPricing']);
    Route::delete('/pricing/override/{retailerId}', [PricingController::class, 'reset']);

    // Wallet Top-up Approvals & Adjustments
    Route::get('/wallet/overview', [WalletController::class, 'overview']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/adjust', [WalletController::class, 'adjust']);
    Route::get('/wallet/fund-requests', [WalletController::class, 'fundRequests']);
    Route::put('/wallet/fund-requests/{id}/approve', [WalletController::class, 'approveFundRequest']);
    Route::put('/wallet/fund-requests/{id}/reject', [WalletController::class, 'rejectFundRequest']);

    // Verification Records & Audit Trail
    Route::get('/records', [VerificationController::class, 'records']);

    // Reports & Analytics
    Route::get('/reports', [ReportController::class, 'index']);

    // Platform Settings & Configuration
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings/{section}', [SettingController::class, 'update']);
    Route::put('/profile', [SettingController::class, 'updateProfile']);
    Route::put('/password', [SettingController::class, 'changePassword']);
    Route::get('/audit-logs', [SettingController::class, 'auditLogs']);
    Route::post('/demo-data/reset', [SettingController::class, 'resetDemoData']);
});

// 3. Retailer Portal Endpoints
Route::prefix('retailer')->group(function () {
    Route::get('/overview', [RetailerDashboardController::class, 'overview']);
    Route::get('/profile', [RetailerDashboardController::class, 'profile']);
    Route::put('/profile', [RetailerDashboardController::class, 'updateProfile']);
    Route::put('/password', [RetailerDashboardController::class, 'changePassword']);
    Route::post('/logout-all', [RetailerDashboardController::class, 'logoutAll']);
    Route::post('/fund-requests', [WalletController::class, 'requestTopup']);
    Route::get('/records', [VerificationController::class, 'records']);
    Route::get('/verifications', [VerificationController::class, 'records']);
    Route::get('/history', [VerificationController::class, 'records']);
});

// 4. Authenticated Notifications
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
});

// 4. Shared Catalog & Services
Route::get('/services', [KycServiceController::class, 'index']);
Route::get('/pricing', [PricingController::class, 'index']);

// 4. Wallet Operations
Route::prefix('wallet')->group(function () {
    Route::get('/transactions', [WalletController::class, 'transactions']);
    Route::get('/fund-requests', [WalletController::class, 'fundRequests']);
    Route::post('/fund-requests', [WalletController::class, 'requestTopup']);
    Route::post('/adjust', [WalletController::class, 'adjust']);
    Route::put('/fund-requests/{id}/approve', [WalletController::class, 'approveFundRequest']);
    Route::put('/fund-requests/{id}/reject', [WalletController::class, 'rejectFundRequest']);
});

// 5. Retailers List (Direct Access Fallback)
Route::get('/retailers', [RetailerController::class, 'index']);
Route::post('/retailers', [RetailerController::class, 'store']);
Route::put('/retailers/{id}/status', [RetailerController::class, 'toggleStatus']);

// 6. KYC Verifications
Route::prefix('verifications')->group(function () {
    Route::post('/verify', [VerificationController::class, 'verify']);
    Route::get('/records', [VerificationController::class, 'records']);
});
