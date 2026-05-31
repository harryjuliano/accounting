<?php

use App\Http\Controllers\Api\Integrations\InventoryEventController;
use App\Http\Controllers\Api\Integrations\VendorInvoiceEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations/inventory')->group(function () {
    Route::post('/events', [InventoryEventController::class, 'store'])->name('api.integrations.inventory.events.store');
});

Route::prefix('integrations/vendor-invoices')->group(function () {
    Route::post('/events', [VendorInvoiceEventController::class, 'store'])->name('api.integrations.vendor-invoices.events.store');
});
