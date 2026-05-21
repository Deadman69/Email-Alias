<?php

use App\Http\Controllers\Api\V1\AliasController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\EmailController;
use App\Http\Controllers\Api\V1\Admin\AliasController as AdminAliasController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

    // ── User API ──────────────────────────────────────────────────────────────
    Route::get('aliases', [AliasController::class, 'index'])->name('api.aliases.index');
    Route::post('aliases', [AliasController::class, 'store'])->name('api.aliases.store');
    Route::get('aliases/{alias}', [AliasController::class, 'show'])->name('api.aliases.show');
    Route::delete('aliases/{alias}', [AliasController::class, 'destroy'])->name('api.aliases.destroy');

    Route::get('aliases/{alias}/emails', [EmailController::class, 'index'])->name('api.aliases.emails.index');
    Route::get('aliases/{alias}/emails/{email}', [EmailController::class, 'show'])->name('api.aliases.emails.show');
    Route::delete('aliases/{alias}/emails/{email}', [EmailController::class, 'destroy'])->name('api.aliases.emails.destroy');

    Route::get('emails/{email}/attachments', [AttachmentController::class, 'index'])->name('api.emails.attachments.index');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('api.attachments.download');

    // ── Admin API (requires role >= admin + appropriate token ability) ────────
    Route::prefix('admin')->middleware(['admin'])->group(function (): void {
        Route::get('aliases', [AdminAliasController::class, 'index'])->name('api.admin.aliases.index');
        Route::delete('aliases/{alias}', [AdminAliasController::class, 'destroy'])->name('api.admin.aliases.destroy');

        Route::get('users', [UserController::class, 'index'])->name('api.admin.users.index');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('api.admin.users.update');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('api.admin.logs.index');
    });
});
