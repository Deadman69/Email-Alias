<?php

namespace App\Livewire\Admin;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\SettingService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Platform Settings')]
#[Layout('layouts.app')]
class Settings extends Component
{
    // ── General ───────────────────────────────────────────────────────────────────
    public string $app_name   = '';
    public string $app_locale = 'en';

    // ── Auth ─────────────────────────────────────────────────────────────────────
    public bool   $sso_enabled          = false;
    public string $azure_client_id      = '';
    public string $azure_client_secret  = '';
    public string $azure_tenant_id      = '';
    public bool   $local_auth_enabled   = true;
    public bool   $registration_enabled = false;

    // ── Security ─────────────────────────────────────────────────────────────────
    public bool $two_factor_required = false;

    // ── Aliases ───────────────────────────────────────────────────────────────────
    public int    $alias_max_per_user     = 20;
    public bool   $alias_allow_permanent  = true;
    public string $alias_default_type     = 'session';

    // ── Email (displayed in MB in the UI, stored in bytes) ────────────────────────
    public int  $alias_max_email_size_mb       = 10;
    public int  $alias_max_attachment_size_mb  = 5;
    public int  $cleanup_email_retention_days  = 30;
    public bool $admin_can_read_emails         = false;

    // ── Active tab ────────────────────────────────────────────────────────────────
    public string $activeTab = 'general';

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function mount(SettingService $settings): void
    {
        $this->app_name   = (string) $settings->get('app_name', 'EmailAlias');
        $this->app_locale = (string) $settings->get('app_locale', 'en');
        $this->sso_enabled         = (bool) $settings->get('sso_enabled', false);
        $this->azure_client_id     = (string) $settings->get('azure_client_id', '');
        // Never expose the client secret in Livewire state — leave blank.
        // The view shows a hint when a value is already stored.
        // On save, an empty field means "keep the existing value".
        $this->azure_client_secret = '';
        $this->azure_tenant_id     = (string) $settings->get('azure_tenant_id', '');
        $this->local_auth_enabled  = (bool) $settings->get('local_auth_enabled', true);
        $this->registration_enabled = (bool) $settings->get('registration_enabled', false);

        $this->two_factor_required = (bool) $settings->get('two_factor_required', false);

        $this->alias_max_per_user    = (int) $settings->get('alias_max_per_user', 20);
        $this->alias_allow_permanent = (bool) $settings->get('alias_allow_permanent', true);
        $this->alias_default_type    = (string) $settings->get('alias_default_type', 'session');

        $this->alias_max_email_size_mb      = (int) round($settings->get('alias_max_email_size_bytes', 10485760) / 1024 / 1024);
        $this->alias_max_attachment_size_mb = (int) round($settings->get('alias_max_attachment_size_bytes', 5242880) / 1024 / 1024);
        $this->cleanup_email_retention_days = (int) $settings->get('cleanup_email_retention_days', 30);
        $this->admin_can_read_emails        = (bool) $settings->get('admin_can_read_emails', false);
    }

    // ── Computed ──────────────────────────────────────────────────────────────────

    #[Computed]
    public function appUrl(): string
    {
        return config('app.url', '');
    }

    // ── Actions ───────────────────────────────────────────────────────────────────

    public function save(SettingService $settings, AuditLogger $auditLogger): void
    {
        $this->validate([
            'app_name'                      => 'required|string|max:100',
            'app_locale'                    => 'required|in:en,fr',
            'azure_client_id'               => 'nullable|string|max:255',
            'azure_client_secret'           => 'nullable|string|max:500',
            'azure_tenant_id'               => 'nullable|string|max:255',
            'alias_max_per_user'            => 'required|integer|min:1|max:1000',
            'alias_default_type'            => 'required|in:session,duration,permanent',
            'alias_max_email_size_mb'       => 'required|integer|min:1|max:100',
            'alias_max_attachment_size_mb'  => 'required|integer|min:1|max:50',
            'cleanup_email_retention_days'  => 'required|integer|min:0|max:3650',
        ]);

        // Both SSO and local auth cannot be disabled simultaneously.
        if (! $this->sso_enabled && ! $this->local_auth_enabled) {
            $this->addError('local_auth_enabled', __('At least one authentication method must be enabled.'));

            return;
        }

        $data = [
            'app_name'                         => $this->app_name,
            'app_locale'                       => $this->app_locale,
            'sso_enabled'                      => $this->sso_enabled,
            'azure_client_id'                  => $this->azure_client_id,
            'azure_tenant_id'                  => $this->azure_tenant_id,
            'local_auth_enabled'               => $this->local_auth_enabled,
            'registration_enabled'             => $this->registration_enabled,
            'two_factor_required'              => $this->two_factor_required,
            'alias_max_per_user'               => $this->alias_max_per_user,
            'alias_allow_permanent'            => $this->alias_allow_permanent,
            'alias_default_type'               => $this->alias_default_type,
            'alias_max_email_size_bytes'       => $this->alias_max_email_size_mb * 1024 * 1024,
            'alias_max_attachment_size_bytes'  => $this->alias_max_attachment_size_mb * 1024 * 1024,
            'cleanup_email_retention_days'     => $this->cleanup_email_retention_days,
            'admin_can_read_emails'            => $this->admin_can_read_emails,
        ];

        // Only update the Azure client secret if the admin explicitly entered a new value.
        // An empty field means "keep the existing encrypted value".
        if ($this->azure_client_secret !== '') {
            $data['azure_client_secret'] = $this->azure_client_secret;
            // Clear from Livewire state immediately after saving
            $this->azure_client_secret = '';
        }

        $settings->fill($data);

        $auditLogger->log(AuditEvent::SettingsSaved, null, [
            'actor' => Auth::user()->email,
        ]);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}
