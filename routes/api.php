<?php

use App\Http\Controllers\Api\Integrations\InventoryEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations/inventory')->group(function () {
    Route::post('/events', [InventoryEventController::class, 'store'])->name('api.integrations.inventory.events.store');
});
