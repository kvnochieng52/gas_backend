<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerMobileController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerDepositController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepositConfigController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RatePlanController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/recent-transactions', [DashboardController::class, 'recentTransactions']);
        Route::get('/gas-distribution', [DashboardController::class, 'gasDistribution']);
        Route::get('/revenue-chart', [DashboardController::class, 'revenueChart']);
        Route::get('/device-status', [DashboardController::class, 'deviceStatus']);
    });

    Route::apiResource('customers', CustomerController::class);
    Route::post('customers/{customer}/top-up', [CustomerController::class, 'initiateTopUp']);
    Route::get('customers/{customer}/credit-history', [CustomerController::class, 'creditHistory']);

    Route::apiResource('devices', DeviceController::class);
    Route::post('devices/{device}/valve', [DeviceController::class, 'controlValve']);
    Route::get('devices/{device}/readings', [DeviceController::class, 'readings']);

    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{transaction}', [PaymentController::class, 'show']);
    Route::get('payments/query/{checkoutRequestId}', [PaymentController::class, 'queryStatus']);

    // Alerts (critical + tamper)
    Route::get('alerts/summary', [AlertController::class, 'summary']);
    Route::get('alerts/critical', [AlertController::class, 'criticalDevices']);
    Route::get('alerts/critical/recent', [AlertController::class, 'recentCritical']);
    Route::get('alerts/tampered', [AlertController::class, 'tamperedDevices']);
    Route::get('alerts/tamper-events/recent', [AlertController::class, 'recentTamperEvents']);
    Route::post('alerts/devices/{device}/flag', [AlertController::class, 'flagTamper']);
    Route::post('alerts/devices/{device}/resolve', [AlertController::class, 'resolveTamper']);

    // Finance & Revenue
    Route::get('finance/stats', [FinanceController::class, 'stats']);
    Route::get('finance/revenue-by-day', [FinanceController::class, 'revenueByDay']);
    Route::get('finance/transactions', [FinanceController::class, 'transactions']);
    Route::get('finance/export-csv', [FinanceController::class, 'exportCsv']);

    // User management (admin only)
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    // Roles & permissions
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    Route::post('roles/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);
    Route::get('permissions', [RoleController::class, 'permissions']);
    Route::post('permissions', [RoleController::class, 'createPermission']);
    Route::delete('permissions/{permission}', [RoleController::class, 'deletePermission']);

    // Rate plans
    Route::get('rate-plans', [RatePlanController::class, 'index']);
    Route::post('rate-plans', [RatePlanController::class, 'store']);
    Route::put('rate-plans/{ratePlan}', [RatePlanController::class, 'update']);
    Route::post('rate-plans/{ratePlan}/set-active', [RatePlanController::class, 'setActive']);
    Route::delete('rate-plans/{ratePlan}', [RatePlanController::class, 'destroy']);

    // Deposit configurations (admin only)
    Route::get('deposit-configs', [DepositConfigController::class, 'index']);
    Route::post('deposit-configs', [DepositConfigController::class, 'store']);
    Route::put('deposit-configs/{depositConfiguration}', [DepositConfigController::class, 'update']);
    Route::post('deposit-configs/{depositConfiguration}/set-active', [DepositConfigController::class, 'setActive']);
    Route::delete('deposit-configs/{depositConfiguration}', [DepositConfigController::class, 'destroy']);

    // Customer deposits
    Route::get('deposits', [CustomerDepositController::class, 'index']);
    Route::get('deposits/customer-status', [CustomerDepositController::class, 'customerStatus']);
    Route::post('deposits/{customer}/mpesa', [CustomerDepositController::class, 'initiateMpesa']);
    Route::post('deposits/{customer}/cash', [CustomerDepositController::class, 'recordCash']);
    Route::get('deposits/query/{checkoutRequestId}', [CustomerDepositController::class, 'queryStatus']);
});

// Callbacks — no auth (called by Safaricom)
Route::post('payments/mpesa/callback', [PaymentController::class, 'mpesaCallback']);
Route::post('deposits/mpesa/callback', [CustomerDepositController::class, 'mpesaCallback']);

// ── Customer Mobile App ──────────────────────────────────────────────────────
Route::prefix('mobile')->group(function () {
    // Public: login & first-time PIN setup
    Route::post('auth/login', [CustomerMobileController::class, 'login']);
    Route::post('auth/set-pin', [CustomerMobileController::class, 'setPin']);

    // Protected with Sanctum token (customer tokenable)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout',                                    [CustomerMobileController::class, 'logout']);
        Route::get('profile',                                         [CustomerMobileController::class, 'profile']);
        Route::get('transactions',                                    [CustomerMobileController::class, 'transactions']);
        Route::post('topup',                                          [CustomerMobileController::class, 'topup']);
        Route::get('topup/status/{checkoutRequestId}',                [CustomerMobileController::class, 'topupStatus']);
    });
});
