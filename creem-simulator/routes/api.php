<?php

use App\Http\Controllers\Api\CreemSimulatorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/products', [CreemSimulatorController::class, 'showProduct']);
    Route::get('/products/search', [CreemSimulatorController::class, 'listProducts']);
    Route::post('/products', [CreemSimulatorController::class, 'createProduct']);

    Route::get('/customers', [CreemSimulatorController::class, 'showCustomer']);
    Route::get('/customers/list', [CreemSimulatorController::class, 'listCustomers']);
    Route::post('/customers/billing', [CreemSimulatorController::class, 'createCustomerBillingLink']);

    Route::get('/subscriptions', [CreemSimulatorController::class, 'subscriptions']);
    Route::get('/subscriptions/search', [CreemSimulatorController::class, 'subscriptions']);
    Route::post('/subscriptions/{subscription}/cancel', [CreemSimulatorController::class, 'cancelSubscription']);
    Route::post('/subscriptions/{subscription}/pause', [CreemSimulatorController::class, 'pauseSubscription']);
    Route::post('/subscriptions/{subscription}/resume', [CreemSimulatorController::class, 'resumeSubscription']);
    Route::post('/subscriptions/{subscription}/upgrade', [CreemSimulatorController::class, 'upgradeSubscription']);
    Route::post('/subscriptions/{subscription}', [CreemSimulatorController::class, 'updateSubscription']);

    Route::get('/transactions', [CreemSimulatorController::class, 'showTransaction']);
    Route::get('/transactions/search', [CreemSimulatorController::class, 'listTransactions']);

    Route::post('/checkouts', [CreemSimulatorController::class, 'createCheckout']);
    Route::get('/checkouts', [CreemSimulatorController::class, 'showCheckout']);
});
