<?php

use App\Http\Controllers\Internal\InboundEmailController;
use Illuminate\Support\Facades\Route;

/*
 * Internal routes — only accessible from the Docker internal network.
 * Protected by EnsureInternalRequest middleware (shared secret header).
 * These routes must NEVER be exposed to the public internet.
 */
Route::middleware(['internal', 'throttle:120,1'])->prefix('internal')->group(function () {
    Route::post('/inbound', [InboundEmailController::class, 'store'])->name('internal.inbound');
});
