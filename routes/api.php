<?php

use App\Http\Controllers\Api\Integrations\IntegrationEventController;
use App\Http\Controllers\Api\Integrations\InventoryEventController;
use App\Http\Controllers\Api\Integrations\VendorInvoiceEventController;
use App\Http\Controllers\Api\Integrations\VendorPaymentEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations')->group(function () {
    Route::post('/events', [IntegrationEventController::class, 'store'])->name('api.integrations.events.store');
});

Route::prefix('integrations/inventory')->group(function () {
    Route::post('/events', [InventoryEventController::class, 'store'])->name('api.integrations.inventory.events.store');
});



Route::prefix('integrations/vendor-payments')->group(function () {
    Route::get('/events', fn () => response()->json([
        'message' => 'Vendor payment integration endpoint is available. Send vendor payment events with the POST method.',
        'method' => 'POST',
        'path' => '/api/integrations/vendor-payments/events',
        'required_headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ]))->name('api.integrations.vendor-payments.events.show');

    Route::post('/events', [VendorPaymentEventController::class, 'store'])->name('api.integrations.vendor-payments.events.store');
});

Route::prefix('integrations/vendor-invoices')->group(function () {
    Route::get('/events', fn () => response()->json([
        'message' => 'Vendor invoice integration endpoint is available. Send vendor invoice events with the POST method.',
        'method' => 'POST',
        'path' => '/api/integrations/vendor-invoices/events',
        'required_headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ]))->name('api.integrations.vendor-invoices.events.show');

    Route::post('/events', [VendorInvoiceEventController::class, 'store'])->name('api.integrations.vendor-invoices.events.store');
});
