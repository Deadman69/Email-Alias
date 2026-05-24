<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\SamlController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Internal\InboundEmailController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Mailbox\EmailDownloadController;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Settings as AdminSettings;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\Mailbox\Dashboard;
use App\Livewire\Mailbox\Inbox;
use App\Livewire\Mailbox\ViewEmail;
use App\Livewire\UserDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// ── Health check (visibility controlled by platform setting) ─────────────────
Route::get('/health', HealthController::class)->middleware('health.access')->name('health');

// ── SSO — OAuth2 / OIDC (Azure AD, Keycloak, generic OIDC) ───────────────────
Route::middleware('guest')->group(function () {
    Route::get('/auth/sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
});

// ── SSO — SAML 2.0 ───────────────────────────────────────────────────────────
// Metadata is public (IdP needs to read it to configure the SP trust).
// ACS (POST from IdP) must be exempt from CSRF — it comes from the IdP server.
Route::get('/auth/saml/metadata', [SamlController::class, 'metadata'])->name('saml.metadata');
Route::get('/auth/saml/error', [SamlController::class, 'error'])->name('saml.error');
Route::middleware('guest')->group(function () {
    Route::get('/auth/saml/login', [SamlController::class, 'login'])->name('saml.login');
    Route::get('/auth/saml/sls', [SamlController::class, 'sls'])->name('saml.sls');
});
// ACS is POST from the IdP — exempt from CSRF via VerifyCsrfToken middleware
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->post('/auth/saml/acs', [SamlController::class, 'acs'])->name('saml.acs');

// ── API documentation — redirects to Scramble's auto-generated Swagger UI ────
// Scramble serves its own UI at /docs/api and spec at /docs/api.json.
// The named route 'api.docs' is kept so existing links in the UI don't break.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/api/docs', '/docs/api')->name('api.docs');
});

// ── Mailbox (authenticated users) ────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', UserDashboard::class)->name('dashboard');
    Route::get('/mailbox', Dashboard::class)->name('mailbox.dashboard');
    Route::get('/mailbox/{alias}', Inbox::class)->name('mailbox.inbox');
    Route::get('/mailbox/emails/{email}', ViewEmail::class)->name('mailbox.email');
    Route::get('/mailbox/emails/{email}/download', [EmailDownloadController::class, 'eml'])->name('mailbox.email.download');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachment.show');
});

// ── Admin panel (admin + super_admin) ─────────────────────────────────────────
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboard::class)->name('dashboard');
    Route::get('/users', AdminUsers::class)->name('users');
    Route::get('/audit', AuditLogViewer::class)->name('audit');
});

// ── Super Admin panel (platform configuration) ────────────────────────────────
Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings', AdminSettings::class)->name('settings');
});

require __DIR__ . '/settings.php';
