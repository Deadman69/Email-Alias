<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Internal\InboundEmailController;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Mailbox\Dashboard;
use App\Livewire\Mailbox\Inbox;
use App\Livewire\Mailbox\ViewEmail;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// ── SSO (Azure AD) ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/auth/sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
});

// ── Mailbox (authenticated users) ────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/dashboard', '/mailbox')->name('dashboard');
    Route::get('/mailbox', Dashboard::class)->name('mailbox.dashboard');
    Route::get('/mailbox/{alias}', Inbox::class)->name('mailbox.inbox');
    Route::get('/mailbox/emails/{email}', ViewEmail::class)->name('mailbox.email');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachment.show');
});

// ── Admin panel ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboard::class)->name('dashboard');
    Route::get('/audit', AuditLogViewer::class)->name('audit');
});

require __DIR__ . '/settings.php';
